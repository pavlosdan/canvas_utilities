<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_style_guide\StyleGuide;

use Drupal\canvas_utilities_style_guide\Entity\StyleGuideDefinition;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Symfony\Component\Yaml\Yaml;

/**
 * Discovers and normalizes style-guide definitions for a theme.
 */
final class StyleGuideDiscovery {

  private const CACHE_PREFIX = 'canvas_utilities_style_guides:';

  /**
   * Constructs the discovery service.
   */
  public function __construct(
    private readonly ThemeExtensionList $themeExtensionList,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CacheBackendInterface $cache,
    private readonly string $appRoot,
  ) {}

  /**
   * Gets all definitions resolved for a theme.
   *
   * @return array<string, array<string, mixed>>
   *   Definitions keyed by public guide ID.
   */
  public function getDefinitions(string $theme): array {
    $cid = self::CACHE_PREFIX . $theme;
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $themes = $this->themeExtensionList->getList();
    if (!isset($themes[$theme])) {
      throw new \InvalidArgumentException(sprintf('Unknown theme "%s".', $theme));
    }

    $ancestry = array_keys($themes[$theme]->base_themes ?? []);
    $ancestry[] = $theme;
    $definitions = [];
    foreach ($ancestry as $provider_theme) {
      $extension = $themes[$provider_theme];
      $path = $this->appRoot . '/' . $extension->getPath() . '/' . $provider_theme . '.canvas_utilities.yml';
      if (!is_file($path)) {
        continue;
      }
      $document = Yaml::parseFile($path);
      if (!is_array($document) || ($document['schema_version'] ?? NULL) !== 1 || !is_array($document['style_guides'] ?? NULL)) {
        throw new \UnexpectedValueException(sprintf('Invalid Canvas Utilities manifest in theme "%s".', $provider_theme));
      }
      foreach ($document['style_guides'] as $id => $definition) {
        if (!is_array($definition)) {
          continue;
        }
        $this->assertMachineName((string) $id, 'guide');
        $definitions[$id] = $this->normalize($id, $definition, [
          'type' => 'theme',
          'id' => $provider_theme,
          'label' => (string) $extension->info['name'],
        ]);
      }
    }

    $storage = $this->entityTypeManager->getStorage('canvas_utilities_sg_definition');
    $entities = $storage->loadByProperties(['status' => TRUE]);
    uasort($entities, static function ($a, $b): int {
      assert($a instanceof StyleGuideDefinition);
      assert($b instanceof StyleGuideDefinition);
      $a_data = $a->toDefinition();
      $b_data = $b->toDefinition();
      return [$a_data['weight'], $a->id()] <=> [$b_data['weight'], $b->id()];
    });
    foreach ($entities as $entity) {
      assert($entity instanceof StyleGuideDefinition);
      $data = $entity->toDefinition();
      if ($data['theme'] !== $theme) {
        continue;
      }
      $id = (string) $data['id'];
      $this->assertMachineName($id, 'guide');
      if (isset($definitions[$id]) && $data['replaces'] === '') {
        throw new \UnexpectedValueException(sprintf('Style guide "%s" must explicitly replace the existing definition.', $id));
      }
      $definitions[$id] = $this->normalize($id, $data, $data['source']);
    }

    uasort(
      $definitions,
      static fn (array $a, array $b): int => [
        $a['weight'],
        $a['label'],
        $a['id'],
      ] <=> [
        $b['weight'],
        $b['label'],
        $b['id'],
      ],
    );
    $this->cache->set($cid, $definitions, CacheBackendInterface::CACHE_PERMANENT, [
      'config:core.extension',
      'config:system.theme',
      'config:canvas_utilities_sg_definition_list',
    ]);
    return $definitions;
  }

  /**
   * Gets one resolved definition.
   *
   * @return array<string, mixed>|null
   *   The definition, or NULL when it does not exist.
   */
  public function getDefinition(string $theme, string $guide): ?array {
    return $this->getDefinitions($theme)[$guide] ?? NULL;
  }

  /**
   * Validates and normalizes a UI-authored definition candidate.
   *
   * @param string $id
   *   Public guide ID.
   * @param array<string, mixed> $definition
   *   Candidate definition.
   *
   * @return array<string, mixed>
   *   Normalized definition.
   */
  public function validateCandidate(string $id, array $definition): array {
    $this->assertMachineName($id, 'guide');
    return $this->normalize($id, $definition, [
      'type' => 'config',
      'id' => $definition['entity_id'] ?? $id,
      'label' => (string) ($definition['label'] ?? $id),
    ]);
  }

  /**
   * Normalizes and minimally validates a definition.
   *
   * @param string $id
   *   Public guide ID.
   * @param array<string, mixed> $definition
   *   Raw definition.
   * @param array<string, string> $source
   *   Source metadata.
   *
   * @return array<string, mixed>
   *   Normalized definition.
   */
  private function normalize(string $id, array $definition, array $source): array {
    $definition += [
      'label' => $id,
      'description' => '',
      'weight' => 0,
      'preview' => ['path' => '/'],
      'contexts' => ['default' => ['label' => 'Default', 'selector' => ':root']],
      'groups' => [],
    ];
    if (!is_array($definition['contexts']) || !is_array($definition['groups'])) {
      throw new \UnexpectedValueException(sprintf('Style guide "%s" contexts and groups must be mappings.', $id));
    }
    $preview_path = (string) ($definition['preview']['path'] ?? '/');
    if (!str_starts_with($preview_path, '/') || str_starts_with($preview_path, '//') || preg_match('#^/(admin|user/(login|password))(?:/|$)#', $preview_path)) {
      throw new \UnexpectedValueException(sprintf('Style guide "%s" has an unsafe preview path.', $id));
    }
    $definition['preview'] = ['path' => $preview_path];
    foreach ($definition['contexts'] as $context_id => $context) {
      $this->assertMachineName((string) $context_id, 'context');
      if (!is_array($context) || !isset($context['selector']) || !$this->isSafeSelector((string) $context['selector'])) {
        throw new \UnexpectedValueException(sprintf('Style guide "%s" has an invalid context "%s".', $id, $context_id));
      }
      $definition['contexts'][$context_id] += ['label' => $context_id];
    }
    foreach ($definition['groups'] as $group_id => &$group) {
      $this->assertMachineName((string) $group_id, 'group');
      if (!is_array($group) || !is_array($group['controls'] ?? NULL)) {
        throw new \UnexpectedValueException(sprintf('Style guide "%s" has an invalid group "%s".', $id, $group_id));
      }
      $group += ['label' => $group_id, 'weight' => 0];
      foreach ($group['controls'] as $control_id => &$control) {
        $this->assertMachineName((string) $control_id, 'control');
        if (!is_array($control) || !isset($control['type'], $control['targets']) || !is_array($control['targets'])) {
          throw new \UnexpectedValueException(sprintf('Style guide "%s" has an invalid control "%s".', $id, $control_id));
        }
        $control += ['label' => $control_id, 'description' => '', 'default' => [], 'constraints' => []];
        foreach ($control['targets'] as $context_id => $target) {
          if (!isset($definition['contexts'][$context_id]) || !is_array($target) || !preg_match('/^--[a-zA-Z0-9_-]+$/', (string) ($target['property'] ?? ''))) {
            throw new \UnexpectedValueException(sprintf('Control "%s" has an invalid CSS target.', $control_id));
          }
        }
      }
      unset($control);
    }
    unset($group);

    $normalized = [
      'id' => $id,
      'label' => (string) $definition['label'],
      'description' => (string) $definition['description'],
      'weight' => (int) $definition['weight'],
      'preview' => $definition['preview'],
      'contexts' => $definition['contexts'],
      'groups' => $definition['groups'],
      'source' => $source,
    ];
    $normalized['definitionHash'] = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    return $normalized;
  }

  /**
   * Validates a definition identifier.
   */
  private function assertMachineName(string $id, string $kind): void {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $id)) {
      throw new \UnexpectedValueException(sprintf('Invalid %s ID "%s".', $kind, $id));
    }
  }

  /**
   * Allows deliberately narrow context selectors in the first release.
   */
  private function isSafeSelector(string $selector): bool {
    return $selector === ':root' || preg_match('/^\.[a-zA-Z][a-zA-Z0-9_-]*$/', $selector) === 1;
  }

}

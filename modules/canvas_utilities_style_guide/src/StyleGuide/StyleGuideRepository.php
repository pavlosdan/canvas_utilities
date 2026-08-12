<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_style_guide\StyleGuide;

use Drupal\canvas_utilities_style_guide\Entity\StyleGuideValues;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Coordinates live values and per-user style-guide drafts.
 */
final class StyleGuideRepository {

  /**
   * Constructs a style-guide repository.
   */
  public function __construct(
    private readonly StyleGuideDiscovery $discovery,
    private readonly StyleGuideValueValidator $validator,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PrivateTempStoreFactory $tempStoreFactory,
  ) {}

  /**
   * Gets client data for all guides in a theme.
   *
   * @return array<int, array<string, mixed>>
   *   Resolved guide data.
   */
  public function getGuides(string $theme): array {
    $result = [];
    foreach ($this->discovery->getDefinitions($theme) as $guide => $definition) {
      $live = $this->loadLive($theme, $guide);
      $draft = $this->tempStoreFactory->get('canvas_utilities_style_guide')->get($this->draftKey($theme, $guide));
      $result[] = $definition + [
        'liveValues' => $live['values'],
        'draft' => $draft,
        'baseHash' => $this->hash($definition, $live['values']),
      ];
    }
    return $result;
  }

  /**
   * Saves a private draft.
   *
   * @param string $theme
   *   Theme machine name.
   * @param string $guide
   *   Public guide ID.
   * @param array<string, mixed> $values
   *   Candidate override values.
   * @param string $base_hash
   *   Hash of the live state from which the draft was made.
   *
   * @return array<string, mixed>
   *   Stored draft data.
   */
  public function saveDraft(string $theme, string $guide, array $values, string $base_hash): array {
    $definition = $this->requireDefinition($theme, $guide);
    $live = $this->loadLive($theme, $guide);
    $current_hash = $this->hash($definition, $live['values']);
    if (!hash_equals($current_hash, $base_hash)) {
      throw new \RuntimeException('The live style guide changed after this editor was opened.');
    }
    $draft = [
      'baseHash' => $base_hash,
      'values' => $this->validator->validate($definition, $values),
      'updated' => time(),
    ];
    $this->tempStoreFactory->get('canvas_utilities_style_guide')->set($this->draftKey($theme, $guide), $draft);
    return $draft;
  }

  /**
   * Publishes the current account's draft.
   *
   * @return array<string, mixed>
   *   Published values and new base hash.
   */
  public function publish(string $theme, string $guide): array {
    $definition = $this->requireDefinition($theme, $guide);
    $store = $this->tempStoreFactory->get('canvas_utilities_style_guide');
    $key = $this->draftKey($theme, $guide);
    $draft = $store->get($key);
    if (!is_array($draft)) {
      throw new \UnderflowException('There is no draft to publish.');
    }
    $live = $this->loadLive($theme, $guide);
    if (!hash_equals($this->hash($definition, $live['values']), (string) $draft['baseHash'])) {
      throw new \RuntimeException('The live style guide changed after this draft was created.');
    }

    $id = self::entityId($theme, $guide);
    $storage = $this->entityTypeManager->getStorage('canvas_utilities_sg_values');
    $entity = $storage->load($id) ?? $storage->create(['id' => $id]);
    assert($entity instanceof StyleGuideValues);
    $entity->set('label', sprintf('%s: %s', $theme, $definition['label']));
    $entity->set('theme', $theme);
    $entity->set('guide_id', $guide);
    $entity->set('definition_hash', $definition['definitionHash']);
    $entity->set('values', $draft['values']);
    $entity->save();
    $store->delete($key);

    return [
      'values' => $draft['values'],
      'baseHash' => $this->hash($definition, $draft['values']),
    ];
  }

  /**
   * Loads live values and metadata.
   *
   * @return array{values: array<string, mixed>, definitionHash: string}
   *   Stored state.
   */
  private function loadLive(string $theme, string $guide): array {
    $entity = $this->entityTypeManager->getStorage('canvas_utilities_sg_values')->load(self::entityId($theme, $guide));
    if (!$entity instanceof StyleGuideValues) {
      return ['values' => [], 'definitionHash' => ''];
    }
    return [
      'values' => $entity->getValues(),
      'definitionHash' => $entity->getDefinitionHash(),
    ];
  }

  /**
   * Gets a definition or throws a client-safe domain exception.
   *
   * @return array<string, mixed>
   *   The definition.
   */
  private function requireDefinition(string $theme, string $guide): array {
    return $this->discovery->getDefinition($theme, $guide) ?? throw new \OutOfBoundsException('The requested style guide does not exist.');
  }

  /**
   * Hashes a normalized definition and its values.
   *
   * @param array<string, mixed> $definition
   *   Normalized definition.
   * @param array<string, mixed> $values
   *   Normalized override values.
   */
  private function hash(array $definition, array $values): string {
    return hash('sha256', json_encode([$definition['definitionHash'], $values], JSON_THROW_ON_ERROR));
  }

  /**
   * Builds the per-user draft key.
   */
  private function draftKey(string $theme, string $guide): string {
    return $theme . ':' . $guide;
  }

  /**
   * Builds the config-entity ID for a theme and guide.
   */
  public static function entityId(string $theme, string $guide): string {
    return $theme . '__' . $guide;
  }

}

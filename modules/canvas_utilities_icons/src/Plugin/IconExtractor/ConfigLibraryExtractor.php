<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Plugin\IconExtractor;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\canvas_utilities_icons\Entity\IconLibrary;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Icon\Attribute\IconExtractor;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\IconDefinitionInterface;
use Drupal\Core\Theme\Icon\IconExtractorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bridges managed libraries to Drupal core's experimental Icon API.
 *
 * This class deliberately isolates the experimental core API from the rest of
 * Canvas Utilities so it can be replaced if Drupal changes that contract.
 */
#[IconExtractor(
  id: 'canvas_utilities_config',
  label: new TranslatableMarkup('Canvas Utilities config libraries'),
  description: new TranslatableMarkup('Loads sanitized SVGs from managed Canvas Utilities libraries.'),
)]
final class ConfigLibraryExtractor extends IconExtractorBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the extractor.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Service container.
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      (string) $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, array<string, mixed>>
   *   Discovered icons keyed by full icon ID.
   */
  public function discoverIcons(): array {
    $icons = [];
    foreach ($this->entityTypeManager->getStorage('canvas_utilities_icon_lib')->loadMultiple() as $entity) {
      if (!$entity instanceof IconLibrary || !$entity->status()) {
        continue;
      }
      foreach ($entity->getIcons() as $icon) {
        $icon_id = $entity->id() . '--' . $icon['id'];
        $full_id = IconDefinition::createIconId($this->configuration['id'], $icon_id);
        $icons[$full_id] = [
          'source' => $icon['uri'],
          'group' => $icon['group'] ?: $entity->label(),
          'viewBox' => $icon['viewBox'],
        ];
      }
    }
    return $icons;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $icon_data
   *   Icon discovery data.
   */
  public function loadIcon(array $icon_data): ?IconDefinitionInterface {
    if (!isset($icon_data['icon_id'], $icon_data['source'], $icon_data['viewBox'])) {
      return NULL;
    }
    $path = $this->fileSystem->realpath((string) $icon_data['source']);
    $markup = $path === FALSE ? FALSE : file_get_contents($path);
    if (!is_string($markup)) {
      return NULL;
    }
    $document = new \DOMDocument();
    $loaded = $document->loadXML($markup, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    if (!$loaded || $document->documentElement?->localName !== 'svg') {
      return NULL;
    }
    $content = '';
    foreach ($document->documentElement->childNodes as $child) {
      $content .= $document->saveXML($child);
    }
    return $this->createIcon(
      (string) $icon_data['icon_id'],
      (string) $icon_data['source'],
      isset($icon_data['group']) ? (string) $icon_data['group'] : NULL,
      [
        'content' => new FormattableMarkup($content, []),
        'attributes' => new Attribute(),
        'viewBox' => (string) $icon_data['viewBox'],
      ],
    );
  }

}

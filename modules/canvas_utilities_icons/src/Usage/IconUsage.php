<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Usage;

use Drupal\canvas_utilities_icons\Canvas\IconPropContract;
use Drupal\canvas_utilities_icons\Entity\IconLibrary;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Reports where the icons of a library are actually used.
 *
 * Usage is derived on demand rather than tracked in its own table. An icon
 * selection is stored verbatim as its root-relative URL inside a component
 * instance's `inputs`, so every reference can be found by searching the places
 * component trees are persisted. A tracking table would have to hook every
 * write path — content saves, revisions, config import, auto-save — and would
 * only duplicate what this can derive, for a question that is asked when
 * something is deleted rather than on every request.
 *
 * Four surfaces hold references, and a scan that checks only the first reports
 * false "unused":
 * 1. content entity component trees, current and historical revisions;
 * 2. config entities that embed component trees (patterns, content templates,
 *    page regions);
 * 3. code component prop examples, which can default to an icon;
 * 4. unpublished auto-save drafts.
 *
 * The result is advisory. An icon URL hard-coded in a component's own JS or
 * CSS, or assembled at runtime, cannot be found this way, so callers should
 * warn rather than block.
 */
final class IconUsage {

  /**
   * The Canvas field type that stores component trees.
   */
  private const COMPONENT_TREE_FIELD_TYPE = 'component_tree';

  /**
   * The key-value collection holding Canvas auto-save drafts.
   *
   * @see \Drupal\canvas\AutoSave\AutoSaveManager::AUTO_SAVE_STORE
   */
  private const AUTO_SAVE_STORE = 'canvas.auto_save';

  /**
   * Config entity types that can embed a component tree.
   */
  private const TREE_CONFIG_ENTITY_TYPES = ['pattern', 'content_template', 'page_region'];

  /**
   * Constructs the icon usage service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyValueFactoryInterface $keyValue,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Finds everything that references any icon in a library.
   *
   * @param \Drupal\canvas_utilities_icons\Entity\IconLibrary $library
   *   The library to report on.
   * @param \Drupal\canvas_utilities_icons\Usage\IconUsageScope $scope
   *   Which content revisions to consider.
   *
   * @return array<string, list<array{type: string, label: string, id: string}>>
   *   References keyed by icon ID. Icons with no references are omitted.
   */
  public function forLibrary(IconLibrary $library, IconUsageScope $scope = IconUsageScope::Active): array {
    $icons_by_url = [];
    foreach ($library->getIcons() as $icon) {
      $icons_by_url[$this->fileUrlGenerator->generateString($icon['uri'])] = $icon['id'];
    }
    if ($icons_by_url === []) {
      return [];
    }

    $usage = [];
    foreach ([
      ...$this->findInContent($icons_by_url, $scope),
      ...$this->findInConfig($icons_by_url),
      ...$this->findInAutoSaves($icons_by_url),
    ] as [$icon_id, $record]) {
      // The same entity can reference one icon more than once; report it once.
      $usage[$icon_id][$record['type'] . ':' . $record['id']] = $record;
    }

    $result = [];
    foreach ($usage as $icon_id => $records) {
      $result[$icon_id] = array_values($records);
    }
    ksort($result);
    return $result;
  }

  /**
   * Counts all references to a library's icons.
   */
  public function countForLibrary(IconLibrary $library, IconUsageScope $scope = IconUsageScope::Active): int {
    return array_sum(array_map('count', $this->forLibrary($library, $scope)));
  }

  /**
   * Searches content entity component trees.
   *
   * @param array<string, string> $icons_by_url
   *   Icon IDs keyed by public URL.
   *
   * @return list<array{0: string, 1: array{type: string, label: string, id: string}}>
   *   Icon ID and reference pairs.
   */
  private function findInContent(array $icons_by_url, IconUsageScope $scope): array {
    $candidates = $this->componentIdsWithIconProps();
    // No component anywhere can hold an icon, so no instance can reference one.
    if ($candidates === []) {
      return [];
    }

    $found = [];
    $field_map = $this->entityFieldManager->getFieldMapByFieldType(self::COMPONENT_TREE_FIELD_TYPE);
    foreach ($field_map as $entity_type_id => $fields) {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $definition = $this->entityTypeManager->getDefinition($entity_type_id);
      foreach (array_keys($fields) as $field_name) {
        foreach ($this->revisionQueries($entity_type_id, $scope) as $query) {
          // `component_id` is indexed while `inputs` is not, so narrowing to
          // the components that can hold an icon keeps this off a full scan.
          $query->condition($field_name . '.component_id', $candidates, 'IN');
          // One broad match on the shared asset directory rather than one
          // condition per icon; exact URLs are matched in PHP below.
          $query->condition($field_name . '.inputs', '%' . $this->assetPathFragment() . '%', 'LIKE');
          /** @var array<int|string, int|string> $ids */
          $ids = $query->execute();
          if (!$ids) {
            continue;
          }
          if ($definition->isRevisionable() && $scope === IconUsageScope::All) {
            // An all-revisions query is keyed by revision ID.
            assert($storage instanceof RevisionableStorageInterface);
            $entities = $storage->loadMultipleRevisions(array_keys($ids));
          }
          else {
            // Otherwise the values are entity IDs, and the default and latest
            // queries can both return the same entity.
            $entities = $storage->loadMultiple(array_values(array_unique(array_values($ids))));
          }
          foreach ($entities as $entity) {
            if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field_name)) {
              continue;
            }
            foreach ($this->matchIcons($entity->get($field_name)->getValue(), $icons_by_url) as $icon_id) {
              $found[] = [
                $icon_id,
                [
                  'type' => (string) $definition->getLabel(),
                  'label' => (string) ($entity->label() ?? $entity->id()),
                  'id' => $entity_type_id . ':' . $entity->id(),
                ],
              ];
            }
          }
        }
      }
    }
    return $found;
  }

  /**
   * Builds one entity query per revision selection in scope.
   *
   * @return list<\Drupal\Core\Entity\Query\QueryInterface>
   *   Prepared queries.
   */
  private function revisionQueries(string $entity_type_id, IconUsageScope $scope): array {
    $definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$definition->isRevisionable()) {
      return [$storage->getQuery()->accessCheck(FALSE)];
    }
    if ($scope === IconUsageScope::All) {
      return [$storage->getQuery()->accessCheck(FALSE)->allRevisions()];
    }
    // "Active" means what an editor can currently see: the published revision
    // and the newest draft. A pending revision can be newer than the default
    // one, so neither query alone covers both.
    return [
      $storage->getQuery()->accessCheck(FALSE)->currentRevision(),
      $storage->getQuery()->accessCheck(FALSE)->latestRevision(),
    ];
  }

  /**
   * Returns the icons referenced anywhere inside a value.
   *
   * @param array<string, string> $icons_by_url
   *   Icon IDs keyed by public URL.
   *
   * @return list<string>
   *   Matching icon IDs.
   */
  private function matchIcons(mixed $data, array $icons_by_url): array {
    $haystack = implode("\n", $this->collectStrings($data));
    $matched = [];
    foreach ($icons_by_url as $url => $icon_id) {
      if (str_contains($haystack, $url)) {
        $matched[] = $icon_id;
      }
    }
    return $matched;
  }

  /**
   * Collects every string in a structure, descending into nested JSON.
   *
   * Canvas stores a component instance's `inputs` as a JSON string inside the
   * field value, so the URL sits one encoding level down. Re-encoding the
   * whole structure would leave that inner string escaped (`\/sites\/…`) and
   * a plain URL would never match it. Decoding as we descend compares real
   * values instead of their escaped representation, at any depth.
   *
   * @return list<string>
   *   Every string found, including decoded nested ones.
   */
  private function collectStrings(mixed $value): array {
    if (is_string($value)) {
      $strings = [$value];
      $decoded = json_decode($value, TRUE);
      if (is_array($decoded)) {
        foreach ($this->collectStrings($decoded) as $nested) {
          $strings[] = $nested;
        }
      }
      return $strings;
    }
    if (is_array($value)) {
      $strings = [];
      foreach ($value as $item) {
        foreach ($this->collectStrings($item) as $nested) {
          $strings[] = $nested;
        }
      }
      return $strings;
    }
    return [];
  }

  /**
   * Searches config entities that embed component trees or icon defaults.
   *
   * Config entities are few and small, so each is encoded and searched whole.
   * That covers embedded component trees and code component prop examples with
   * the same pass.
   *
   * @param array<string, string> $icons_by_url
   *   Icon IDs keyed by public URL.
   *
   * @return list<array{0: string, 1: array{type: string, label: string, id: string}}>
   *   Icon ID and reference pairs.
   */
  private function findInConfig(array $icons_by_url): array {
    $found = [];
    foreach ([...self::TREE_CONFIG_ENTITY_TYPES, 'js_component'] as $entity_type_id) {
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        continue;
      }
      $definition = $this->entityTypeManager->getDefinition($entity_type_id);
      foreach ($this->entityTypeManager->getStorage($entity_type_id)->loadMultiple() as $entity) {
        foreach ($this->matchIcons($entity->toArray(), $icons_by_url) as $icon_id) {
          {
            $found[] = [
              $icon_id,
              [
                'type' => (string) $definition->getLabel(),
                'label' => (string) ($entity->label() ?? $entity->id()),
                'id' => $entity_type_id . ':' . $entity->id(),
              ],
            ];
          }
        }
      }
    }
    return $found;
  }

  /**
   * Searches unpublished Canvas auto-save drafts.
   *
   * @param array<string, string> $icons_by_url
   *   Icon IDs keyed by public URL.
   *
   * @return list<array{0: string, 1: array{type: string, label: string, id: string}}>
   *   Icon ID and reference pairs.
   */
  private function findInAutoSaves(array $icons_by_url): array {
    $found = [];
    foreach ($this->keyValue->get(self::AUTO_SAVE_STORE)->getAll() as $key => $draft) {
      if (!is_array($draft)) {
        continue;
      }
      foreach ($this->matchIcons($draft['data'] ?? $draft, $icons_by_url) as $icon_id) {
        {
          $found[] = [
            $icon_id,
            [
              'type' => 'Unpublished draft',
              'label' => (string) ($draft['label'] ?? $key),
              'id' => 'auto_save:' . $key,
            ],
          ];
        }
      }
    }
    return $found;
  }

  /**
   * Lists Component config entity IDs whose props can hold an icon.
   *
   * Canvas records the widget it resolved for every prop of every exposed
   * component, so asking which components ended up with the icon picker is
   * both cheaper and more reliable than re-deriving it from each component
   * source's own schema. It is also source-agnostic: code components and
   * single-directory components are covered by the same lookup.
   *
   * @return list<string>
   *   Component config entity IDs.
   */
  private function componentIdsWithIconProps(): array {
    $ids = [];
    foreach ($this->configFactory->listAll('canvas.component.') as $name) {
      // Canvas versions components, and an existing instance may still be
      // pinned to an older version. Checking only the active version would
      // miss instances placed before an icon prop was removed, which on a
      // deletion guard means reporting "unused" for an icon still on a page.
      $versions = $this->configFactory->get($name)->get('versioned_properties');
      if (!is_array($versions)) {
        continue;
      }
      foreach ($versions as $version) {
        $definitions = $version['settings']['prop_field_definitions'] ?? NULL;
        if (!is_array($definitions)) {
          continue;
        }
        foreach ($definitions as $definition) {
          if (is_array($definition) && ($definition['field_widget'] ?? NULL) === IconPropContract::WIDGET_ID) {
            $ids[] = substr($name, strlen('canvas.component.'));
            continue 3;
          }
        }
      }
    }
    return $ids;
  }

  /**
   * Returns the coarse fragment used to shortlist rows in SQL.
   *
   * Deliberately free of slashes. Component inputs are stored as JSON, and
   * JSON encoders escape forward slashes, so the stored text reads
   * `canvas-utilities\\/icons\\/`. A pattern containing plain slashes matches
   * nothing. Precision is not needed here in any case: this only shortlists
   * rows, and exact URLs are compared in PHP afterwards.
   */
  private function assetPathFragment(): string {
    return 'canvas-utilities';
  }

}

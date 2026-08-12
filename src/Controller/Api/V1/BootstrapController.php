<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\Controller\Api\V1;

use Drupal\canvas_utilities\Plugin\CanvasUtilitiesCapabilityManager;
use Drupal\canvas_utilities\PendingChanges\PendingChangesAdapterInterface;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides the Design system application's initial state.
 */
final class BootstrapController {

  /**
   * Constructs a bootstrap controller.
   */
  public function __construct(
    private readonly CanvasUtilitiesCapabilityManager $capabilityManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ThemeExtensionList $themeExtensionList,
    private readonly CsrfTokenGenerator $csrfTokenGenerator,
    private readonly AccountInterface $account,
    private readonly PendingChangesAdapterInterface $pendingChangesAdapter,
  ) {}

  /**
   * Returns client-safe application state.
   */
  public function __invoke(): JsonResponse {
    /** @var array<int, array{id: string, label: string, description: string, route: string, weight: int}> $capabilities */
    $capabilities = [];
    foreach ($this->capabilityManager->getDefinitions() as $definition) {
      $permissions = $definition['permissions'];
      if ($permissions !== [] && !$this->hasAnyPermission($permissions)) {
        continue;
      }
      $capability = $this->capabilityManager->createInstance($definition['id']);
      $capabilities[] = $capability->toArray();
    }
    array_multisort(
      array_column($capabilities, 'weight'),
      SORT_ASC,
      array_column($capabilities, 'label'),
      SORT_NATURAL | SORT_FLAG_CASE,
      $capabilities,
    );

    $theme_config = $this->configFactory->get('system.theme');
    $default_theme = (string) $theme_config->get('default');
    $themes = [];
    foreach ($this->themeExtensionList->getList() as $id => $theme) {
      // The theme extension list populates this runtime property.
      // @phpstan-ignore property.notFound
      if ($theme->status === 0) {
        continue;
      }
      $themes[] = [
        'id' => $id,
        'label' => (string) $theme->info['name'],
        'default' => $id === $default_theme,
      ];
    }

    return new JsonResponse([
      'apiVersion' => '1.0',
      'application' => [
        'name' => 'Design system',
        'canvasPath' => Url::fromRoute('canvas.boot.app', [
          'extension_id' => 'canvas_utilities_design_system',
        ])->toString(),
      ],
      'activeTheme' => $default_theme,
      'themes' => $themes,
      'capabilities' => $capabilities,
      'permissions' => [
        'publish' => $this->account->hasPermission('publish canvas utilities changes'),
        'administerStyleGuideDefinitions' => $this->account->hasPermission('administer canvas utilities style guide definitions'),
      ],
      'reviewWorkflow' => [
        'adapter' => $this->pendingChangesAdapter->id(),
        'supportsStaging' => $this->pendingChangesAdapter->supportsStaging(),
        'description' => $this->pendingChangesAdapter->description(),
      ],
      'csrfToken' => $this->csrfTokenGenerator->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
    ], headers: [
      'Cache-Control' => 'private, no-store',
    ]);
  }

  /**
   * Determines whether the current account has any supplied permission.
   *
   * @param string[] $permissions
   *   Permission names.
   */
  private function hasAnyPermission(array $permissions): bool {
    foreach ($permissions as $permission) {
      if ($this->account->hasPermission($permission)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities\GeneratedCss;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileExistsException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Materializes generated CSS as immutable, content-addressed public files.
 */
final class GeneratedCssAssetManager {

  /**
   * Constructs the generated CSS asset manager.
   */
  public function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly FileSystemInterface $fileSystem,
    private readonly LockBackendInterface $lock,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Gets the current immutable asset for a provider and theme.
   */
  public function getAsset(GeneratedCssProviderInterface $provider, string $theme): ?GeneratedCssAsset {
    $cid = $this->cacheId($provider->id(), $theme);
    if ($cached = $this->cache->get($cid)) {
      $asset = $cached->data;
      if ($asset instanceof GeneratedCssAsset && file_exists($asset->uri)) {
        return $asset;
      }
      if ($asset === NULL) {
        return NULL;
      }
    }

    $source = $provider->build($theme);
    $tags = Cache::mergeTags($provider->getCacheTags($theme), [self::getCacheTag($provider->id(), $theme)]);
    if ($source === NULL) {
      $this->cache->set($cid, NULL, CacheBackendInterface::CACHE_PERMANENT, $tags);
      return NULL;
    }

    $css = $this->normalizeCss($source->css);
    $tags = Cache::mergeTags($tags, $source->cacheTags);
    $fingerprint = hash('sha256', $css);
    $uri = $this->assetUri($provider->id(), $theme, $fingerprint);

    try {
      $this->materialize($uri, $css);
    }
    catch (\Throwable $exception) {
      $this->loggerFactory->get('canvas_utilities')->error('Unable to write generated CSS asset "@uri": @message', [
        '@uri' => $uri,
        '@message' => $exception->getMessage(),
      ]);
      return NULL;
    }

    $asset = new GeneratedCssAsset($provider->id(), $theme, $fingerprint, $uri);
    $this->cache->set($cid, $asset, CacheBackendInterface::CACHE_PERMANENT, $tags);
    return $asset;
  }

  /**
   * Returns the cache tag used for a provider/theme asset descriptor.
   */
  public static function getCacheTag(string $providerId, string $theme): string {
    return 'canvas_utilities_generated_css:' . $providerId . ':' . $theme;
  }

  /**
   * Builds the descriptor cache ID.
   */
  private function cacheId(string $providerId, string $theme): string {
    return 'asset:' . $providerId . ':' . $theme;
  }

  /**
   * Builds an immutable public URI for generated CSS.
   */
  private function assetUri(string $providerId, string $theme, string $fingerprint): string {
    if (!preg_match('/^[a-z][a-z0-9_-]*$/', $providerId) || !preg_match('/^[a-z][a-z0-9_]*$/', $theme)) {
      throw new \InvalidArgumentException('Generated CSS provider and theme IDs must be machine names.');
    }
    return sprintf('public://canvas-utilities/generated-css/%s/%s.%s.css', $theme, $providerId, $fingerprint);
  }

  /**
   * Normalizes generated source before hashing and writing it.
   */
  private function normalizeCss(string $css): string {
    return rtrim(str_replace(["\r\n", "\r"], "\n", $css)) . "\n";
  }

  /**
   * Writes an asset once without replacing a previous content-addressed file.
   */
  private function materialize(string $uri, string $css): void {
    if (file_exists($uri)) {
      return;
    }
    $directory = $this->fileSystem->dirname($uri);
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new FileException(sprintf('The directory "%s" is not writable.', $directory));
    }

    $lockName = 'canvas_utilities:generated_css:' . hash('sha256', $uri);
    if (!$this->lock->acquire($lockName, 30.0)) {
      throw new \RuntimeException('Timed out waiting to write a generated CSS asset.');
    }

    $temporary = NULL;
    try {
      if (file_exists($uri)) {
        return;
      }
      $temporary = $this->fileSystem->tempnam($directory, '.canvas-utilities-');
      $this->fileSystem->saveData($css, $temporary, FileExists::Replace);
      try {
        $this->fileSystem->move($temporary, $uri, FileExists::Error);
      }
      catch (FileExistsException) {
        // Another webhead completed the same immutable write first.
      }
    }
    finally {
      if (is_string($temporary) && file_exists($temporary)) {
        $this->fileSystem->delete($temporary);
      }
      $this->lock->release($lockName);
    }
  }

}

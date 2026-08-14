<?php

declare(strict_types=1);

namespace Drupal\canvas_utilities_icons\Usage;

/**
 * Selects which content revisions an icon usage scan considers.
 *
 * Canvas treats this as a policy choice rather than a fact: its own component
 * audit deliberately checks only the latest revision when deciding whether a
 * code component may be deleted, because considering every historical revision
 * is too restrictive in practice.
 *
 * @see \Drupal\canvas\Audit\RevisionAuditEnum
 */
enum IconUsageScope {

  /**
   * The default and latest revisions only.
   *
   * This is what an editor means by "in use": the icon appears on a live page,
   * or in the newest draft of one. An icon that only survives in superseded
   * revisions does not block anything, but deleting it does change how those
   * revisions render if they are ever restored or previewed.
   */
  case Active;

  /**
   * Every revision, including superseded ones.
   */
  case All;

}

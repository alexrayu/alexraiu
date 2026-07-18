<?php

/**
 * @file
 * One-off importer: content-drafts/**.md -> article nodes.
 *
 * Run: ddev drush scr scripts/import-drafts.php
 *
 * Idempotent: matches an existing article by exact title and updates it,
 * otherwise creates a new one. Safe to re-run.
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\Yaml\Yaml;

$draftsDir = dirname(DRUPAL_ROOT) . '/content-drafts';
$converter = new GithubFlavoredMarkdownConverter();
$termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

// Map lowercase Audience label -> tid.
$audienceMap = [];
foreach ($termStorage->loadByProperties(['vid' => 'audience']) as $term) {
  $audienceMap[strtolower($term->label())] = $term->id();
}

/**
 * Resolve a Topics (tags) term by name, creating it if absent.
 */
$resolveTag = function (string $name) use ($termStorage): int {
  $existing = $termStorage->loadByProperties(['vid' => 'tags', 'name' => $name]);
  if ($existing) {
    return (int) reset($existing)->id();
  }
  $term = Term::create(['vid' => 'tags', 'name' => $name]);
  $term->save();
  return (int) $term->id();
};

$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($draftsDir, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
  if ($file->getExtension() === 'md' && $file->getFilename() !== 'INDEX.md') {
    $files[] = $file->getPathname();
  }
}
sort($files);

$created = 0;
$updated = 0;
foreach ($files as $path) {
  $raw = file_get_contents($path);
  // Split leading front matter (--- ... ---) from body.
  if (!preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $raw, $m)) {
    echo "SKIP (no front matter): $path\n";
    continue;
  }
  $fm = Yaml::parse($m[1]);
  $bodyMd = ltrim($m[2]);

  $title = (string) ($fm['title'] ?? '');
  if ($title === '') {
    echo "SKIP (no title): $path\n";
    continue;
  }

  $audienceKey = strtolower(trim((string) ($fm['audience'] ?? '')));
  if (!isset($audienceMap[$audienceKey])) {
    echo "SKIP (unknown audience '$audienceKey'): $path\n";
    continue;
  }

  $tagTids = [];
  foreach ((array) ($fm['tags'] ?? []) as $tag) {
    $tag = trim((string) $tag);
    if ($tag !== '') {
      $tagTids[] = ['target_id' => $resolveTag($tag)];
    }
  }

  $bodyHtml = $converter->convert($bodyMd)->getContent();

  // Idempotency: match existing article by title.
  $matches = $nodeStorage->loadByProperties(['type' => 'article', 'title' => $title]);
  $node = $matches ? reset($matches) : Node::create(['type' => 'article']);

  $node->set('title', $title);
  $node->set('body', ['value' => $bodyHtml, 'format' => 'full_html']);
  $node->set('field_summary', (string) ($fm['summary'] ?? ''));
  $node->set('field_tags', $tagTids);
  $node->set('field_audience', ['target_id' => $audienceMap[$audienceKey]]);
  $node->setPublished();
  if (!$node->getOwnerId()) {
    $node->setOwnerId(1);
  }
  $node->save();

  if ($matches) {
    $updated++;
    echo "UPDATED [{$node->id()}] $title\n";
  }
  else {
    $created++;
    echo "CREATED [{$node->id()}] $title\n";
  }
}

echo "\nDone. Created: $created, Updated: $updated, Files: " . count($files) . "\n";

<?php

namespace Drupal\server_general\Plugin\EntityViewBuilder;

use Drupal\intl_date\IntlDate;
use Drupal\media\MediaInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifier;
use Drupal\node\NodeInterface;
use Drupal\server_general\EntityDateTrait;
use Drupal\server_general\EntityViewBuilder\NodeViewBuilderAbstract;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Node News" plugin.
 *
 * @EntityViewBuilder(
 *   id = "node.news",
 *   label = @Translation("Node - News"),
 *   description = "Node view builder for News bundle."
 * )
 */
class NodeNews extends NodeViewBuilderAbstract {

  use EntityDateTrait;

  /**
   * The message notification service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected MessageNotifier $messageNotifier;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $build = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $build->messageNotifier = $container->get('message_notify.sender');

    return $build;
  }

  /**
   * Build full view mode.
   *
   * @param array $build
   *   The existing build.
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array.
   */
  public function buildFull(array $build, NodeInterface $entity) {
    $this->messenger()->addMessage('Add your Node News elements in \Drupal\server_general\Plugin\EntityViewBuilder\NodeNews');

    // Header.
    $element = $this->buildHeroImageAndTitle($entity, 'field_featured_image');
    // No wrapper, as the hero image takes the full width.
    $build[] = $element;

    // Tags.
    $element = $this->buildTags($entity);
    $build[] = $this->wrapElementWideContainer($element);

    // Get the body text, wrap it with `prose` so it's styled.
    $element = $this->buildProcessedText($entity);
    $element = $this->wrapElementProseText($element);
    $build[] = $this->wrapElementWideContainer($element);

    $this->deliverMessage($entity);

    return $build;
  }

  /**
   * Build Teaser view mode.
   *
   * @param array $build
   *   The existing build.
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array.
   */
  public function buildTeaser(array $build, NodeInterface $entity) {
    $media = $this->getReferencedEntityFromField($entity, 'field_featured_image');
    $timestamp = $this->getFieldOrCreatedTimestamp($entity, 'field_publish_date');

    $element = [
      '#theme' => 'server_theme_card',
      '#title' => $entity->label(),
      '#image' => $media instanceof MediaInterface ? $this->buildImageStyle($media, 'card', 'field_media_image') : NULL,
      '#date' => IntlDate::formatPattern($timestamp, 'long'),
      '#url' => $entity->toUrl(),
    ];
    $build[] = $element;

    return $build;
  }

  /**
   * Deliver a Message to all site users.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   */
  protected function deliverMessage(NodeInterface $entity) {
    // Create a single Message. Rest of the Messages would be clones, that may
    // or may not be saved.
    $message_storage = $this->entityTypeManager->getStorage('message');
    /** @var \Drupal\message\MessageInterface $message */
    $message = $message_storage->create([
      'template' => 'news_item',
      'arguments' => [
        [
          '@title' => [
            'pass message' => TRUE,
            'callback' => [static::class, 'getMessageTitle'],
          ],
          '@url' => [
            'pass message' => TRUE,
            'callback' => [static::class, 'getMessageUrl'],
          ],
          '@user' => [
            'pass message' => TRUE,
            'callback' => [static::class, 'getMessageUser'],
          ],
          '@calc' => [
            'pass message' => TRUE,
            'callback' => [static::class, 'getMessageComplexCalc'],
          ],
        ],
      ],
    ]);
    $message->field_news = $entity;
    $message->save();

    // Iterate over all existing users.
    $user_storage = $this->entityTypeManager->getStorage('user');
    $users = $user_storage->loadByProperties(['status' => TRUE]);

    foreach ($users as $user) {
      // Clone the Message, and set the owner to the recipient.
      $cloned_message = $message->createDuplicate();
      $cloned_message->setOwner($user);
      $this->messageNotifier->send($cloned_message, [], 'email');
    }
  }

  /**
   * Get title.
   */
  public static function getMessageTitle(MessageInterface $message): string {
    return $message->field_news->entity->label();
  }

  /**
   * Get URL.
   */
  public static function getMessageUrl(MessageInterface $message): string {
    return $message->field_news->entity->toUrl()->toString();
  }

  /**
   * Get User's name.
   */
  public static function getMessageUser(MessageInterface $message): string {
    return $message->getOwner()->label();
  }

  /**
   * Get complex calc.
   */
  public static function getMessageComplexCalc(MessageInterface $message): string {
    return sprintf('Calculated as %d', rand(1, 9999));
  }

}

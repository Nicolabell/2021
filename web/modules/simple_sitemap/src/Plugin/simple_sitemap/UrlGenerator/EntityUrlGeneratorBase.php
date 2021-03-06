<?php

namespace Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Url;
use Drupal\simple_sitemap\EntityHelper;
use Drupal\simple_sitemap\Logger;
use Drupal\simple_sitemap\Simplesitemap;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Class EntityUrlGeneratorBase
 * @package Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 */
abstract class EntityUrlGeneratorBase extends UrlGeneratorBase {

  /**
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected $languages;

  /**
   * @var string
   */
  protected $defaultLanguageId;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $anonUser;

  /**
   * @var \Drupal\simple_sitemap\EntityHelper
   */
  protected $entityHelper;

  /**
   * UrlGeneratorBase constructor.
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\simple_sitemap\Logger $logger
   * @param \Drupal\simple_sitemap\EntityHelper $entityHelper
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Simplesitemap $generator,
    Logger $logger,
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityHelper $entityHelper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $generator, $logger);
    $this->languages = $language_manager->getLanguages();
    $this->defaultLanguageId = $language_manager->getDefaultLanguage()->getId();
    $this->entityTypeManager = $entity_type_manager;
    $this->anonUser = new AnonymousUserSession();
    $this->entityHelper = $entityHelper;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.generator'),
      $container->get('simple_sitemap.logger'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.entity_helper')
    );
  }

  /**
   * @param array $path_data
   * @param \Drupal\Core\Url $url_object
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUrlVariants(array $path_data, Url $url_object) {
    $url_variants = [];

    if (!$url_object->isRouted()) {
      // Not a routed URL, including only default variant.
      $alternate_urls = $this->getAlternateUrlsForDefaultLanguage($url_object);
    }
    elseif ($this->settings['skip_untranslated']
      && ($entity = $this->entityHelper->getEntityFromUrlObject($url_object)) instanceof ContentEntityBase) {

      /** @var ContentEntityBase $entity */
      $translation_languages = $entity->getTranslationLanguages();
      if (isset($translation_languages[Language::LANGCODE_NOT_SPECIFIED])
        || isset($translation_languages[Language::LANGCODE_NOT_APPLICABLE])) {

        // Content entity's language is unknown, including only default variant.
        $alternate_urls = $this->getAlternateUrlsForDefaultLanguage($url_object);
      }
      else {
        // Including only translated variants of content entity.
        $alternate_urls = $this->getAlternateUrlsForTranslatedLanguages($entity, $url_object);
      }
    }
    else {
      // Not a content entity or including all untranslated variants.
      $alternate_urls = $this->getAlternateUrlsForAllLanguages($url_object);
    }

    foreach ($alternate_urls as $langcode => $url) {
      $url_variants[] = $path_data + [
        'langcode' => $langcode,
          'url' => $url,
          'alternate_urls' => $alternate_urls
        ];
    }

    return $url_variants;
  }

  /**
   * @param \Drupal\Core\Url $url_object
   * @return array
   */
  protected function getAlternateUrlsForDefaultLanguage(Url $url_object) {
    $alternate_urls = [];
    if ($url_object->access($this->anonUser)) {
      $alternate_urls[$this->defaultLanguageId] = $this->replaceBaseUrlWithCustom($url_object
        ->setOption('language', $this->languages[$this->defaultLanguageId])->toString()
      );
    }
    return $alternate_urls;
  }

  /**
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   * @param \Drupal\Core\Url $url_object
   * @return array
   */
  protected function getAlternateUrlsForTranslatedLanguages(ContentEntityBase $entity, Url $url_object) {
    $alternate_urls = [];

    /** @var Language $language */
    foreach ($entity->getTranslationLanguages() as $language) {
      if (!isset($this->settings['excluded_languages'][$language->getId()]) || $language->isDefault()) {
        if ($entity->getTranslation($language->getId())->access('view', $this->anonUser)) {
          $alternate_urls[$language->getId()] = $this->replaceBaseUrlWithCustom($url_object
            ->setOption('language', $language)->toString()
          );
        }
      }
    }
    return $alternate_urls;
  }

  /**
   * @param \Drupal\Core\Url $url_object
   * @return array
   */
  protected function getAlternateUrlsForAllLanguages(Url $url_object) {
    $alternate_urls = [];
    if ($url_object->access($this->anonUser)) {
      foreach ($this->languages as $language) {
        if (!isset($this->settings['excluded_languages'][$language->getId()]) || $language->isDefault()) {
          $alternate_urls[$language->getId()] = $this->replaceBaseUrlWithCustom($url_object
            ->setOption('language', $language)->toString()
          );
        }
      }
    }
    return $alternate_urls;
  }

  /**
   * @param mixed $data_set
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function generate($data_set) {
    $path_data = $this->processDataSet($data_set);
    if (isset($path_data['url']) && $path_data['url'] instanceof Url) {
      $url_object = $path_data['url'];
      unset($path_data['url']);
      return $this->getUrlVariants($path_data, $url_object);
    }
    else {
      return FALSE !== $path_data ? [$path_data] : [];
    }
  }

  /**
   * @param string $entity_type_name
   * @param string $entity_id
   * @return array
   */
  protected function getImages($entity_type_name, $entity_id) {
    $images = [];
    foreach ($this->entityHelper->getEntityImageUrls($entity_type_name, $entity_id) as $url) {
      $images[]['path'] = $this->replaceBaseUrlWithCustom($url);
    }
    return $images;
  }
}

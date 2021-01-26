<?php

namespace Drupal\simple_sitemap\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\simple_sitemap\Simplesitemap;
use Symfony\Component\HttpFoundation\Request;
use Drupal\simple_sitemap\SimplesitemapManager;

/**
 * Class SimplesitemapController
 * @package Drupal\simple_sitemap\Controller
 */
class SimplesitemapController extends ControllerBase {

  /**
   * @var \Drupal\simple_sitemap\Simplesitemap
   */
  protected $generator;

  /**
   * SimplesitemapController constructor.
   * @param \Drupal\simple_sitemap\Simplesitemap $generator
   */
  public function __construct(Simplesitemap $generator) {
    $this->generator = $generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_sitemap.generator')
    );
  }

  /**
   * Returns the whole sitemap variant, its requested chunk,
   * or its sitemap index file.
   * Caches the response in case of expected output, prevents caching otherwise.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *  The request object.
   *
   * @param string $variant
   *  Optional name of sitemap variant.
   *  @see SimplesitemapManager::getSitemapVariants()
   *
   * @throws NotFoundHttpException
   *
   * @return \Symfony\Component\HttpFoundation\Response|false
   *  Returns an XML response.
   */
  public function getSitemap(Request $request, $variant = NULL) {
    $output = $this->generator->setVariants($variant)->getSitemap($request->query->getInt('page'));
    if (!$output) {
      throw new NotFoundHttpException();
    }

    return new Response($output, Response::HTTP_OK, [
      'Content-type' => 'application/xml; charset=utf-8',
      'X-Robots-Tag' => 'noindex, follow',
    ]);
  }

  /**
   * Returns the XML stylesheet for the sitemap.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function getSitemapXsl() {

    // Read the XSL content from the file.
    $module_path = drupal_get_path('module', 'simple_sitemap');
    $xsl_content = file_get_contents($module_path . '/xsl/simple_sitemap.xsl');

    // Replace custom tokens in the XSL content with appropriate values.
    $replacements = [
      '[title]' => $this->t('Sitemap file'),
      '[generated-by]' => $this->t('Generated by the <a href="@link">@module_name</a> Drupal module.', ['@link' => 'https://www.drupal.org/project/simple_sitemap', '@module_name' => 'Simple XML Sitemap']),
      '[number-of-sitemaps]' => $this->t('Number of sitemaps in this index'),
      '[sitemap-url]' => $this->t('Sitemap URL'),
      '[number-of-urls]' => $this->t('Number of URLs in this sitemap'),
      '[url-location]' => $this->t('URL location'),
      '[lastmod]' => $this->t('Last modification date'),
      '[changefreq]' => $this->t('Change frequency'),
      '[priority]' => $this->t('Priority'),
      '[translation-set]' => $this->t('Translation set'),
      '[images]' => $this->t('Images'),
      '[jquery]' => base_path() . 'core/assets/vendor/jquery/jquery.min.js',
      '[jquery-tablesorter]' => base_path() . $module_path . '/xsl/jquery.tablesorter.min.js',
      '[parser-date-iso8601]' => base_path() . $module_path . '/xsl/parser-date-iso8601.min.js',
      '[xsl-js]' => base_path() . $module_path . '/xsl/simple_sitemap.xsl.js',
      '[xsl-css]' => base_path() . $module_path . '/xsl/simple_sitemap.xsl.css',
    ];

    // Output the XSL content.
    return new Response(strtr($xsl_content, $replacements), Response::HTTP_OK, [
      'Content-type' => 'application/xml; charset=utf-8',
      'X-Robots-Tag' => 'noindex, nofollow',
    ]);
  }

}

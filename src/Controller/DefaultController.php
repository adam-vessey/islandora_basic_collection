<?php

namespace Drupal\islandora_basic_collection\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBuilderInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use AbstractObject;

/**
 * Default controller for the islandora_basic_collection module.
 */
class DefaultController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * Callback to determine whether or not to show this modules manage tab.
   */
  public function islandora_basic_collection_manage_access($object = NULL) {
    $object = islandora_object_load($object);
    $perm = islandora_basic_collection_manage_access($object);
    return $perm ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Callback to determine whether or not to show share/migrate actions.
   */
  public function islandora_basic_collection_share_migrate_access($object = NULL) {
    $object = islandora_object_load($object);
    $perm = islandora_basic_collection_share_migrate_access($object);
    return $perm ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * Manage Collection local task.
   *
   * Defines the actions to appear in the collection section of the Manage tab.
   */
  public function islandora_basic_collection_manage_object(AbstractObject $object) {
    module_load_include('inc', 'islandora_basic_collection', 'includes/manage_collection');
    $render_array = ['manage_collection_object' => []];
    $data = islandora_invoke_hook_list(ISLANDORA_BASIC_COLLECTION_BUILD_MANAGE_OBJECT_HOOK, $object->models, [
      $render_array,
      $object,
    ]);
    return $data;
  }

  /**
   * Searches through available collection objects.
   */
  public function islandora_basic_collection_get_collections_filtered(Request $request) {
    $search_value = $request->query->get('q');
    $tuque = islandora_get_tuque_connection();
    $sparql_query = <<<EOQ
SELECT ?pid ?label
WHERE {
  ?pid <fedora-model:label> ?label ;
       <fedora-model:hasModel> <info:fedora/islandora:collectionCModel> .
  FILTER(regex(?label, '$search_value', 'i') || regex(str(?pid), '$search_value', 'i'))
}
EOQ;
    $results = $tuque->repository->ri->sparqlQuery($sparql_query);
    $return = [];
    foreach ($results as $objects) {
      $return[] = [
        'value' => $objects['pid']['value'],
        'label' => $this->t('@label (@pid)', [
          '@label' => $objects['label']['value'],
          '@pid' => $objects['pid']['value'],
        ]),
      ];
    }
    return new JsonResponse($return);
  }

  /**
   * Access callback for ingest.
   *
   * @param \AbstractObject $object
   *   The object to test if we're allowed to ingest... Check that it actually
   *   is a collection and we have sufficient info to show the form.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Allowed if $object represents a collection, we can show the ingest form
   *   and we have permission to ingest; otherwise forbidden.
   */
  public function islandora_basic_collection_ingest_access(AbstractObject $object) {
    $collection_models = islandora_basic_collection_get_collection_content_models();
    $is_a_collection = (
      (count(array_intersect($collection_models, $object->models)) > 0) && isset($object['COLLECTION_POLICY'])
      );

    if (!$is_a_collection) {
      return AccessResult::forbidden();
    }

    module_load_include('inc', 'islandora', 'includes/ingest.form');
    module_load_include('inc', 'islandora_basic_collection', 'includes/ingest.form');
    $configuration = islandora_basic_collection_get_ingest_configuration($object);
    $has_ingest_steps = islandora_ingest_can_display_ingest_form($configuration);

    return AccessResult::allowedIf($has_ingest_steps && islandora_object_access(ISLANDORA_INGEST, $object));
  }

  /**
   * Manage action that for ingestion of an object into the given collection.
   */
  public function islandora_basic_collection_ingest_action(AbstractObject $object) {
    if (($configuration = islandora_basic_collection_get_ingest_configuration($object)) !== FALSE) {
      module_load_include('inc', 'islandora', 'includes/ingest.form');
      return $this->formBuilder->getForm('Drupal\islandora\Form\IslandoraIngestForm', $configuration);
    }
    drupal_not_found();
  }

  /**
   * AJAX callback to get info about the count of objects and collections.
   */
  public function islandora_basic_collection_object_count_callback() {
    module_load_include('inc', 'islandora_basic_collection', 'includes/blocks');
    return islandora_basic_collection_object_count_callback();
  }

}

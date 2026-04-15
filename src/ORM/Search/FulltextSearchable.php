<?php
/**
 * Created by PhpStorm.
 * User: Nivanka Fonseka
 * Date: 02/06/2018
 * Time: 07:23
 */

namespace SilverStripers\ElementalSearch\ORM\Search;

use SilverStripe\Core\Extension;
use SilverStripe\CMS\Search\ContentControllerSearchExtension;
use Exception;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripers\ElementalSearch\Model\SearchDocument;

class FulltextSearchable extends Extension
{

    protected $searchFields;

    protected static $searchable_classes;

    public static function enable($searchableClasses = [SearchDocument::class, File::class])
    {
        $defaultColumns = [
            SearchDocument::class => ['Title', 'Content'],
            File::class => ['Name','Title'],
        ];

        if (!is_array($searchableClasses)) {
            $searchableClasses = [$searchableClasses];
        }

        foreach ($searchableClasses as $class) {
            if (!class_exists($class)) {
                continue;
            }

            if (isset($defaultColumns[$class])) {
                $class::add_extension(sprintf('%s(%s)', static::class, "'" . implode("','", $defaultColumns[$class]) . "''"));
            } else {
                throw new Exception(
                    sprintf("FulltextSearchable::enable() I don't know the default search columns for class '%s'", $class)
                );
            }
        }

        self::$searchable_classes = $searchableClasses;
        if (class_exists(ContentController::class)) {
            ContentController::add_extension(ContentControllerSearchExtension::class);
        }
    }

    public function __construct($searchFields = [])
    {
        parent::__construct();
        if (is_array($searchFields)) {
            $this->searchFields = $searchFields;
        } else {
            $this->searchFields = explode(',', (string) $searchFields);
            foreach ($this->searchFields as &$field) {
                $field = trim($field);
            }
        }
    }

    public static function get_extra_config($class, $extensionClass, $args)
    {
        return [
            'indexes' => [
                'SearchFields' => [
                    'type' => 'fulltext',
                    'name' => 'SearchFields',
                    'columns' => $args,
                ]
            ]
        ];
    }

    public static function get_searchable_classes()
    {
        return self::$searchable_classes;
    }
}

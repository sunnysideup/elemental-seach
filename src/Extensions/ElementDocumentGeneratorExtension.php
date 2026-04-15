<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 12:21 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Extensions;

use Override;
use SilverStripers\ElementalSearch\Model\SearchDocument;

class ElementDocumentGeneratorExtension extends SearchDocumentGenerator
{

    public function getGenerateSearchLink()
    {
        /* @var $element BaseElement */
        $element = $this->getOwner();
        $page = $element->getPage();
        return $page ? $page->Link() : null;
    }

    #[Override]
    public function onAfterWrite()
    {
        return null;
    }

    #[Override]
    public function onAfterDelete()
    {
        return null;
    }

    #[Override]
    public function onAfterPublish()
    {
        if ($this->isThisAStandAloneClass()) {
            self::make_document_for($this->getOwner());
        }

        if (!SearchDocumentGenerator::search_documents_prevented()) {
            $this->makeSearchDocumentForPage();
        }
    }

    public function onBeforeArchive()
    {
        return null;
    }

    #[Override]
    public function onAfterArchive()
    {
        if ($this->isThisAStandAloneClass()) {
            self::delete_doc($this->getOwner());
        }

        if (!SearchDocumentGenerator::search_documents_prevented()) {
            $this->makeSearchDocumentForPage();
        }
    }

    public function makeSearchDocumentForPage()
    {
        /* @var $element BaseElement */
        $element = $this->getOwner();
        $page = $element->getPage();
        if($page) {
            self::make_document_for($page);
        }
    }

    private function isThisAStandAloneClass()
    {
        return ($classes = $this->getStandAloneElementClasses()) && in_array($this->getOwner()::class, $classes);
    }

    public function getStandAloneElementClasses()
    {
        return SearchDocument::config()->get('stand_alone_search_elements');
    }

}

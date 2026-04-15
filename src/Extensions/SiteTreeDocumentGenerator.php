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
use SilverStripe\Control\Director;
use SilverStripe\Versioned\Versioned;

class SiteTreeDocumentGenerator extends SearchDocumentGenerator
{

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
        self::make_document_for($this->getOwner());
    }

    public function onBeforeArchive()
    {
        return null;
    }

    #[Override]
    public function onAfterArchive()
    {
        self::delete_doc($this->getOwner());
    }

    public function getGenerateSearchLink()
    {
        $owner = $this->getOwner();
        if(method_exists($owner, 'Link')) {
            $mode = Versioned::get_reading_mode();
            Versioned::set_reading_mode('Stage.Live');
            $link = Director::absoluteURL($owner->Link());
            $link = str_replace('stage=Stage', '', $link);
            Versioned::set_reading_mode($mode);
            if(str_contains($link, '?')) {
                return $link . '&SearchGen=1';
            }

            return $link . '?SearchGen=1';
        }

        $class = $owner::class;
        throw new Exception(
            sprintf("SearchDocumentGenerator::getGenerateSearchLink() There is no Link method defined on class '%s'", $class)
        );
    }

}

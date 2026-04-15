<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 6/2/18
 * Time: 11:44 AM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\ORM\Connect;

use Override;
use Exception;
use SilverStripe\Assets\File;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Connect\MySQLDatabase as SS_MySQLDatabase;
use SilverStripers\ElementalSearch\Model\SearchDocument;



class MySQLDatabase extends SS_MySQLDatabase
{

    #[Override]
    public function searchEngine(
        $classesToSearch,
        $keywords,
        $start,
        $pageLength,
        $sortBy = "Relevance DESC",
        $extraFilter = "",
        $booleanSearch = false,
        $alternativeFileFilter = "",
        $invertedMatch = false
    ) {

        $documentClass = SearchDocument::class;
        $fileClass = File::class;
        if (!class_exists($documentClass)) {
            throw new Exception('MySQLDatabase->searchEngine() requires "SearchDocument" class');
        }

        if (!class_exists($fileClass)) {
            throw new Exception('MySQLDatabase->searchEngine() requires "File" class');
        }

        $keywords = $this->escapeString($keywords);
        $htmlEntityKeywords = htmlentities($keywords, ENT_NOQUOTES, 'UTF-8');

        $extraFilters = [$documentClass => '', $fileClass => ''];

        $boolean = '';
        if ($booleanSearch) {
            $boolean = "IN BOOLEAN MODE";
        }

        if ($extraFilter) {
            $extraFilters[$documentClass] = ' AND ' . $extraFilter;

            $extraFilters[$fileClass] = $alternativeFileFilter ? ' AND ' . $alternativeFileFilter : $extraFilters[$documentClass];
        }

        // File.ShowInSearch was added later, keep the database driver backwards compatible
        // by checking for its existence first
        $fileTable = DataObject::getSchema()->tableName($fileClass);
        $fields = $this->getSchemaManager()->fieldList($fileTable);
        if (array_key_exists('ShowInSearch', $fields)) {
            $extraFilters[$fileClass] .= " AND ShowInSearch <> 0";
        }

        $limit = (int)$start . ", " . (int)$pageLength;

        $notMatch = $invertedMatch
            ? "NOT "
            : "";
        if ($keywords) {
            $match[$documentClass] = "
				MATCH (Title, Content) AGAINST ('{$keywords}' {$boolean})
				+ MATCH (Title, Content) AGAINST ('{$htmlEntityKeywords}' {$boolean})
			";
            $fileClassSQL = Convert::raw2sql($fileClass);
            $match[$fileClass] = sprintf("MATCH (Name, Title) AGAINST ('%s' %s) AND ClassName = '%s'", $keywords, $boolean, $fileClassSQL);

            // We make the relevance search by converting a boolean mode search into a normal one
            $relevanceKeywords = str_replace(['*', '+', '-'], '', $keywords);
            $htmlEntityRelevanceKeywords = str_replace(['*', '+', '-'], '', $htmlEntityKeywords);
            $relevance[$documentClass] = "MATCH (Title, Content) "
                . sprintf("AGAINST ('%s') ", $relevanceKeywords)
                . sprintf("+ MATCH (Title, Content) AGAINST ('%s')", $htmlEntityRelevanceKeywords);
            $relevance[$fileClass] = sprintf("MATCH (Name, Title) AGAINST ('%s')", $relevanceKeywords);
        } else {
            $relevance[$documentClass] = $relevance[$fileClass] = 1;
            $match[$documentClass] = $match[$fileClass] = "1 = 1";
        }

        // Generate initial DataLists and base table names
        $lists = [];
        $sqlTables = [$documentClass => '', $fileClass => ''];
        foreach ($classesToSearch as $class) {
            $lists[$class] = DataList::create($class)->where($notMatch . $match[$class] . $extraFilters[$class]);
            $sqlTables[$class] = '"' . DataObject::getSchema()->tableName($class) . '"';
        }

        $charset = static::config()->get('charset');

        // Make column selection lists
        $select = [
            $documentClass => [
                "ClassName" => "Type",
                "ID" => "OriginID",
                "ParentID" => sprintf("_%s''", $charset),
                "Title",
                "MenuTitle" => sprintf("_%s''", $charset),
                "URLSegment" => sprintf("_%s''", $charset),
                "Content",
                "LastEdited" => sprintf("_%s''", $charset),
                "Created" => sprintf("_%s''", $charset),
                "Name" => sprintf("_%s''", $charset),
                "Relevance" => $relevance[$documentClass],
                "CanViewType" => "NULL"
            ],
            $fileClass => [
                "ClassName",
                $sqlTables[$fileClass] . '."ID"',
                "ParentID",
                "Title",
                "MenuTitle" => sprintf("_%s''", $charset),
                "URLSegment" => sprintf("_%s''", $charset),
                "Content" => sprintf("_%s''", $charset),
                "LastEdited",
                "Created",
                "Name",
                "Relevance" => $relevance[$fileClass],
                "CanViewType" => "NULL"
            ],
        ];

        // Process and combine queries
        $querySQLs = [];
        $queryParameters = [];
        $totalCount = 0;
        foreach ($lists as $class => $list) {
            /** @var SQLSelect $query */
            $query = $list->dataQuery()->query();

            // There's no need to do all that joining
            $query->setFrom($sqlTables[$class]);
            $query->setSelect($select[$class]);
            $query->setOrderBy([]);

            $querySQLs[] = $query->sql($parameters);
            $queryParameters = array_merge($queryParameters, $parameters);

            $totalCount += $query->unlimitedRowCount();
        }

        $fullQuery = implode(" UNION ", $querySQLs) . sprintf(' ORDER BY %s LIMIT %s', $sortBy, $limit);

        // Get records
        $records = $this->preparedQuery($fullQuery, $queryParameters);
        $objects = [];
        foreach ($records as $record) {
            $object = DataList::create($record['ClassName'])->byID($record['ID']);
            if ($object && $object->canView()) {
                $object->SearchSnippet =  $this->generateSearchSnippet($keywords, $record['Content']);
                $objects[] = $object;
            }
        }

        $list = PaginatedList::create(ArrayList::create($objects));
        $list->setPageStart($start);
        $list->setPageLength($pageLength);
        $list->setTotalItems($totalCount);

        // The list has already been limited by the query above
        $list->setLimitItems(false);

        return $list;
    }

    public function generateSearchSnippet($keywords, $content)
    {
        $snippetLength = 200;
        $content = str_replace('&nbsp;', ' ', $content); // &nbsp; is not playing well with spaces
        $content = preg_replace('/\xc2\xa0/', '', $content);
        $content = preg_replace('/\s+/', ' ', html_entity_decode($content));
        $content = trim((string) $content);

        $length = mb_strlen($content);
        $words = preg_split(
            '/[^\p{L}\p{N}\p{Pc}\p{Pd}@]+/u',
            mb_strtolower($keywords),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $occurrences = $this->findOccurrences($words, $content);
        $start = $this->findUsageBaseOnDensity($occurrences);

        if ($length - $start < $snippetLength) {
            $start = floor($start - ($length - $start) / 2);
        }

        if ($start < 0) {
            $start = 0;
        } else { // we need to get a start of a sentence
            $firstChar = mb_substr($content, $start, 1);
            if ($firstChar === '.') {
                $start += 1; // exclude the dot
            } else {
                $offsetString = mb_substr($content, 0, $start);
                $lastFullStop = mb_strrpos($offsetString, '.');
                if ($lastFullStop !== false) {
                    $start = $lastFullStop + 1;
                } else { // this is the first sentence
                    $start = 0;
                }
            }
        }

        $ret = mb_substr($content, $start, $snippetLength);
        $ret = trim($ret);
        if ($start + $snippetLength < mb_strlen($content)) {
            $ret .= '...';
        }

        return $ret;
    }

    protected function findUsageBaseOnDensity($occurences)
    {
        if (empty($occurences)) {
            return -1;
        }

        $preOffset = 10;
        $start = $occurences[0];
        $nofOccurrences = count($occurences);
        $closest = PHP_INT_MAX;

        if ($nofOccurrences > 2) {
            for ($i = 1; $i < $nofOccurrences; $i++) {
                if ($i + 1 === $nofOccurrences) { // last
                    $diff = $occurences[$i] - $occurences[$i - 1];
                } else {
                    $diff = $occurences[$i + 1] - $occurences[$i];
                }

                if ($diff < $closest) {
                    $closest = $diff;
                    $start = $occurences[$i];
                }
            }
        }

        return $start > $preOffset ? $start - $preOffset : 0;
    }

    protected function findOccurrences($words, $content)
    {
        $occurences = [];
        foreach ($words as $word) {
            $length = mb_strlen((string) $word);
            $occurence = mb_stripos((string) $content, (string) $word);
            while ($occurence !== false) {
                $occurences[] = $occurence;
                $occurence = mb_stripos((string) $content, (string) $word, $occurence + $length);
            }
        }

        $occurences = array_unique($occurences);
        sort($occurences);
        return $occurences;
    }



}

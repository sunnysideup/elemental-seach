<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 12:32 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Tasks;


use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripers\ElementalSearch\Extensions\SearchDocumentGenerator;
use SilverStripers\ElementalSearch\Extensions\SiteTreeDocumentGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class GenerateSearchDocument extends BuildTask
{

    protected string $title = 'Re-generate all search documents';

    protected static string $description = 'Generate search documents for items.';

    protected static string $commandName = 'make-search-docs';

    /**
     * Execute the task
     *
     * @param InputInterface $input
     * @param PolyOutput $output
     * @return int
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        set_time_limit(50000);
        $classes = $this->getAllSearchDocClasses();
        foreach ($classes as $class) {
            foreach ($list = DataList::create($class) as $record) {
                $message = sprintf(
                    'Making record for %s type %s, link %s',
                    $record->getTitle(),
                    $record->ClassName,
                    ClassInfo::hasMethod($record, 'getGenerateSearchLink') ? $record->getGenerateSearchLink() : $record->Title
                );

                $output->writeln($message);
                
                try {
                    SearchDocumentGenerator::make_document_for($record);
                } catch (Exception) {
                }
            }
        }

        return Command::SUCCESS;
    }

    public function getAllSearchDocClasses()
    {
        $list = [];
        foreach (ClassInfo::subclassesFor(DataObject::class) as $class) {
            $configs = Config::inst()->get($class, 'extensions', Config::UNINHERITED);
            if($configs) {
                $valid = in_array(SearchDocumentGenerator::class, $configs)
                    || in_array(SiteTreeDocumentGenerator::class, $configs);

                if ($valid) {
                    $list[] = $class;
                }
            }
        }

        return $list;
    }

}

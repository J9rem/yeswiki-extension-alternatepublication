<?php

/*
 * This file is part of the YesWiki Extension alternativepublication.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Test\Alternatepublication\Service;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Alternatepublication\Service\PdfHelper;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

// TODO update tests with new pdfHelper

class PdfHelperTest extends YesWikiTestCase
{
    /**
     * @covers PdfHelper::__construct
     * @return Wiki
     */
    public function testPdfHelperExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(PdfHelper::class));
        return $wiki;
    }

    /**
     * @depends testPdfHelperExisting
     * @covers PdfHelper::getPageEntriesContent
     * @dataProvider dataProvider
     * @param string $pageTagMode
     * @param string|null $via
     * @param array $bazarlisteIds
     * @param bool $withTemplate
     * @param mixed $expected
     * @param bool $clean
     * @param Wiki $wiki
     */
    public function wiptestGetPageEntriesContent(string $pageTagMode, ?string $via, array $bazarlisteIds, bool $withTemplate, bool $clean, $expected, Wiki $wiki)
    {
        if ($pageTagMode === 'entry') {
            $pageTag = $this->getEntryPageName($withTemplate);
            if ($withTemplate) {
                if (!empty($pageTag)) {
                    list($templateName, $templateContent) = $this->getCustomTemplate($pageTag, false);
                }
                if (empty($pageTag) || ($templateContent === '{{template not found}}')) {
                    // create template for next tests
                    $pageTag = $this->getEntryPageName(false);
                    list($templateName, $templateContent) = $this->getCustomTemplate($pageTag, true);
                } elseif ($templateContent === 'test') {
                    $templatesNameToDelete[] = $templateName;
                }
                $expected["template content"] = $templateContent;
            }
        } elseif ($pageTagMode === 'page') {
            if ($via === 'bazarliste') {
                // $pageTag = $this->getPageTagWithBazar2Publication($bazarlisteIds);
                // if (empty($pageTage)) {
                $pageTag = $this->createPageTagWithBazar2Publication($bazarlisteIds);
                $pageToDelete = $pageTag;
                $bazarlisteIdsCopy = $bazarlisteIds;
                $bazarlisteIds = [];
                foreach ($bazarlisteIdsCopy as $bazarlisteId) {
                    if (isset($expected['template fiche-'.$bazarlisteId])) {
                        $templateName = 'fiche-'.$bazarlisteId.'.tpl.html';
                        $templateContent = $this->createCustomTemplate($templateName);
                        $expected['template fiche-'.$bazarlisteId] = $templateContent;
                        $bazarlisteIds[] = $bazarlisteId;
                        $templatesNameToDelete[] = $templateName;
                    }
                }
                // }
            } else {
                $pageTag = $this->getPageTagWithoutBazar2Publication();
            }
        } else {
            // not existing page
            $pageTag = '\/aa';
        }
        $pdfHelper = $wiki->services->get(PdfHelper::class);
        try {
            $results = $pdfHelper->getPageEntriesContent($pageTag, $via);
        } finally {
            if (!empty($pageToDelete)) {
                $this->deletePage($pageToDelete);
            }
            if ($clean && !empty($templatesNameToDelete)) {
                foreach ($templatesNameToDelete as $templateNameToDelete) {
                    $this->deleteCustomEmptyTemplate($templateNameToDelete);
                }
            }
        }
        if (!empty($expected['entries last-date'])) {
            $this->assertArrayHasKey('entries last-date', $results);
            $this->assertTrue(!empty($results['entries last-date']));
            $this->assertIsString($results['entries last-date']);
            echo "\n\n".$results['entries last-date']."\n\n";
            foreach ($bazarlisteIds as $bazarlisteId) {
                $this->assertArrayHasKey('template fiche-'.$bazarlisteId, $results);
                $this->assertSame($expected['template fiche-'.$bazarlisteId], $results['template fiche-'.$bazarlisteId]);
            }
        } else {
            $this->assertSame($expected, $results);
        }
    }

    public function dataProvider()
    {
        // pageTagMode ,via, bazarlisteIds, withTemplate, clean,expected
        return [
            'page not entry' => ['page',null,[],false,false,[]],
            'page not entry with via without template' => ['page','bazarliste',['3'],false,false,['entries last-date' => '{{date}}']],
            'page not entry with via with template' => ['page','bazarliste',['1'],false,false,['entries last-date' => '{{date}}','template fiche-1' => '{{content}}']],
            'page not entry with via 2 ids with template' => ['page','bazarliste',['1','3'],false,false,['entries last-date' => '{{date}}','template fiche-1' => '{{content}}']],
            'page not entry with via 2 ids with templates' => ['page','bazarliste',['1','4'],false,true,['entries last-date' => '{{date}}','template fiche-1' => '{{content}}','template fiche-2' => '{{content}}']],
            'not existing page' => ['no page',null,[],false,false,[]],
            'not existing page with via' => ['no page','bazarliste',[],false,false,[]],
            'entry without template' => ['entry',null,[],false,false,[]],
            'entry with via without template' => ['entry','bazarliste',[],false,false,[]],
            'entry with template' => ['entry',null,[],true,false,["template content"=>"{{content}}"]],
            'entry with via with template' => ['entry','bazarliste',[],true,true,["template content"=>"{{content}}"]],
        ];
    }

    /**
     * @param bool $withTemplate
     * @return string
     */
    protected function getEntryPageName(bool $withTemplate): string
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        $templateEngine = $wiki->services->get(TemplateEngine::class);
        $GLOBALS['wiki'] = $wiki; // for bazar.fonct.php:82
        $entries = $entryManager->search([]);
        foreach ($entries as $tag => $entry) {
            $formId = $entry['id_typeannonce'];
            if (strval($formId) == strval(intval($formId))) {
                $templateName = '@bazar/fiche-'.trim($formId).'.tpl.html';
                if ($withTemplate == $templateEngine->hasTemplate($templateName)) {
                    return $tag;
                }
            }
        }
        return '';
    }
    /**
     * @eturn null|string
     */
    private function getPageTagWithBazar2Publication(): ?string
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $sqlRequest = 'SELECT tag FROM ' . $dbService->prefixTable('pages') . ' '.
            'WHERE latest = \'Y\' AND comment_on=\'\' AND '.
            'body LIKE \'%{{bazarliste%\' AND '.
            'body LIKE \'%{{bazar2publication}}%\' AND '.
            'tag NOT IN (SELECT DISTINCT resource FROM ' . $dbService->prefixTable('triples') . ' '.'
                WHERE value = "fiche_bazar" AND '.
                'property = "http://outils-reseaux.org/_vocabulary/type"'.
            ') LIMIT 1';
        $pages = $dbService->loadAll($sqlRequest);
        return empty($pages) ? null : $pages[array_key_first($pages)]['tag'] ;
    }

    /**
     * @eturn null|string
     */
    private function getPageTagWithoutBazar2Publication(): ?string
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $sqlRequest = 'SELECT tag FROM ' . $dbService->prefixTable('pages') . ' '.
            'WHERE latest = \'Y\' AND comment_on=\'\' AND '.
            'body NOT LIKE \'%{{bazarliste%\' AND '.
            'body NOT LIKE \'%{{bazar2publication}}%\' AND '.
            'tag NOT IN (SELECT DISTINCT resource FROM ' . $dbService->prefixTable('triples') . ' '.'
                WHERE value = "fiche_bazar" AND '.
                'property = "http://outils-reseaux.org/_vocabulary/type"'.
            ') LIMIT 1';
        $pages = $dbService->loadAll($sqlRequest);
        return empty($pages) ? null : $pages[array_key_first($pages)]['tag'] ;
    }

    /**
     * @param array $bazarlisteIds
     * @return string $pageTag
     */
    private function createPageTagWithBazar2Publication(array $bazarlisteIds): string
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $ids = implode(',', $bazarlisteIds);
        $pageContent = "{{bazarliste id=\"".$ids."\"}}\n{{bazar2publication}}";
        $pageContent = _convert($pageContent, YW_CHARSET, true);
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersUpperCase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $pageTag = '';
            
            $index = rand(0, strlen($charactersUpperCase) - 1);
            $pageTag .= $charactersUpperCase[$index];
    
            for ($i = 0; $i < 8; $i++) {
                $index = rand(0, strlen($characters) - 1);
                $pageTag .= $characters[$index];
            }

            $index = rand(0, strlen($charactersUpperCase) - 1);
            $pageTag .= $charactersUpperCase[$index];
        } while (!empty($pageManager->getOne($pageTag)));
        $pageManager->save($pageTag, $pageContent, "", true);
        return $pageTag;
    }
    /**
     * @param string $pageTag
     */
    private function deletePage(string $pageTag)
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->deleteOrphaned($pageTag);
    }

    /**
     * @param string $pageTag
     * @param bool $createTemplate
     * @return array [string $templateName, string $templateContent]
     */
    protected function getCustomTemplate(string $pageTag, bool $createTemplate = false): array
    {
        $wiki = $this->getWiki();
        $entryManager = $wiki->services->get(EntryManager::class);
        $entry = $entryManager->getOne($pageTag);
        $formId = trim($entry['id_typeannonce']);
        $templateName = 'fiche-'.$formId.'.tpl.html';
        if ($createTemplate) {
            $templateContent = $this->createCustomTemplate($templateName);
        } else {
            $paths = [
                    'custom/templates/bazar/',
                    'custom/templates/bazar/templates/',
                    'themes/tools/bazar/templates/',
                    'themes/tools/bazar/presentation/templates/',
                    'tools/bazar/templates/',
                    'tools/bazar/presentation/templates/',
                ];
            $templateContent = '{{template not found}}' ; // default if template not found
            foreach ($paths as $path) {
                if (file_exists($path.$templateName)) {
                    $templateContent = file_get_contents($path.$templateName);
                    return [$templateName,$templateContent];
                }
            }
        }
        return [$templateName,$templateContent];
    }
    private function createCustomTemplate(string $templateName): string
    {
        if (!file_exists('custom/templates/bazar')) {
            mkdir('custom/templates/bazar', 0777, true);
        }
        $templateContent = 'test';
        file_put_contents('custom/templates/bazar/'.$templateName, $templateContent);
        return $templateContent;
    }
    
    /**
     * @param string $templateName
     */
    protected function deleteCustomEmptyTemplate(string $templateName)
    {
        if (file_exists('custom/templates/bazar/'.$templateName)) {
            unlink('custom/templates/bazar/'.$templateName);
        }
    }
}

<?php

use YesWiki\Core\Service\AssetsManager;

if ($this->HasAccess('read') && isset($this->page['metadatas']['publication-title'])) {
    $GLOBALS['css'] = str_replace(
        $this->services->get(AssetsManager::class)->LinkCSSFile('tools/publication/presentation/styles/publication.css'),
        '',
        $GLOBALS['css'] ?? ''
    );
    $this->AddCSSFile('tools/alternatepublication/presentation/styles/publication.css');
}

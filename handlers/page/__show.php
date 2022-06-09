<?php
global $wiki;

if ($this->HasAccess('read') && isset($this->page['metadatas']['publication-title'])) {
    $wiki->AddCSSFile('tools/alternatepublication/presentation/styles/publication.css');
}

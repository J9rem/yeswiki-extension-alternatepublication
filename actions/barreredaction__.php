<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$plugin_output_new = preg_replace(
    '#'.
    preg_quote('<a class="link-pdf" href="'.
            $this->href('pdf').'" title="'.
            _t('PUBLICATION_EXPORT_PAGE_TO_PDF').'"', '#').
    '[^>]*'.
    preg_quote('><i class="glyphicon glyphicon-book"></i>', '#').
    '[^<]*'.preg_quote('</a>', '#').'\s*'.preg_quote('</div>', '#').'#',
    '</div>',
    $plugin_output_new
);
$plugin_output_new = preg_replace('#</div>#', $this->render('@bazar/entries/_publication_button.twig', ['forPage'=>true])."\n".'</div>', $plugin_output_new);

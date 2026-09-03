<?php

declare(strict_types=1);

namespace Anatolkin\ArpRestaurant\Backend\Editor;

use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Isolates TYPO3 13.4 ButtonBar::makeLinkButton vs 14.3 ComponentFactory.
 */
final class ModuleLinkButtonFactory
{
    public function createLinkButton(ButtonBar $buttonBar): LinkButton
    {
        $factoryClass = 'TYPO3\\CMS\\Backend\\Template\\Components\\ComponentFactory';
        if (class_exists($factoryClass) && method_exists($factoryClass, 'createLinkButton')) {
            $factory = GeneralUtility::makeInstance($factoryClass);
            $button = $factory->createLinkButton();
            if ($button instanceof LinkButton) {
                return $button;
            }
        }

        return $buttonBar->makeLinkButton();
    }
}

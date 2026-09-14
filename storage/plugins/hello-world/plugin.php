<?php
/**
 * Сторонний плагин (открытый код). Размещается в storage/plugins/.
 */

declare(strict_types=1);

add_action('netfree.footer', function () {
    echo "\n" . '<p style="text-align:center;color:#9ca3af;font-size:0.85rem;">Powered by Hello World plugin</p>';
});

<?php
/** @var $l \OCP\IL10N */
/** @var $_ array */

script('contacts', 'adminSettings');         // adds a JavaScript file
?>

<div id="contacts" class="section">
        <h2><?php p($l->t('Contacts')); ?></h2>

                <p>
                        <input id="allowSocialSync"
                               type="checkbox"
                               class="checkbox"
                               <?php if ($_['allowSocialSync'] === 'yes') p('checked'); ?>
                        />
                        <label for="allowSocialSync"><?php p($l->t('Allow updating avatars from social media')); ?></label>
                </p>
</div>

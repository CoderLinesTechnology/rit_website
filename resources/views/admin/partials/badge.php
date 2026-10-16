<?php /** $status */ $s = (string) $status; ?>
<span class="badge badge--<?= e(preg_replace('/[^a-z0-9_]/', '', strtolower($s))) ?>"><?= e(ucfirst(str_replace('_', ' ', $s))) ?></span>

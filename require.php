<?php
if (false === (@include '../main.inc.php')) {
    if (false === @include('../../main.inc.php')) {
        if (false === @include('../../../main.inc.php')) {
            die("Include of main fails");
        }
    }
}

if (!isModEnabled('pricelist')) {
    accessforbidden();
}

<?php

echo date('c'), "\n";
echo 'SLOW RUNNING SCRIPT', "\n\n";

srand(0);
for ($i = 0; $i < 32; $i++) {
    echo str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), "\n";
    usleep(1000000 * 0.25);
}


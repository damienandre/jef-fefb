<?php

declare(strict_types=1);

use Jef\View;

http_response_code(404);
View::render('404.html.php', ['pageTitle' => 'Page non trouvee'], 'layout.php');

<?php

declare(strict_types=1);

// GALPEX no usa colas: todo se procesa en el momento.
return ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']], 'failed' => ['driver' => 'null']];

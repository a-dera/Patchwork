<?php // vi: set fenc=utf-8 ts=4 sw=4 et:

Patchwork\ShutdownHandler::setup();
Patchwork\Shim(register_shutdown_function, Patchwork\ShutdownHandler::register, $callback);

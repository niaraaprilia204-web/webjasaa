<?php

if (!session_id()) {
    session_start();
}

// 1. Load Configurations
require_once '../app/Config/Config.php';
require_once '../app/Config/Theme.php';

// 2. Load Core Classes
require_once '../app/Core/App.php';
require_once '../app/Core/Controller.php';
require_once '../app/Core/Database.php';
require_once '../app/Core/Flasher.php';
require_once '../app/Core/Csrf.php';

// 3. Load Routes & Router
require_once '../app/Routes/Router.php';
require_once '../app/Routes/web.php';

// 4. Load Base Model, Service, Controller & Middleware
require_once '../app/Models/BaseModel.php';
require_once '../app/Services/BaseService.php';
require_once '../app/Middleware/BaseMiddleware.php';
require_once '../app/Controllers/BaseController.php';

// 5. Instantiate Core Router Engine
$app = new App();

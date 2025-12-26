<?php
/**
 * Manual autoloader for AIPS classes.
 * This file serves as a fallback for environments where Composer autoloading is not available.
 * It maps old/current class names to their new file locations in src/.
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {
    // Mapping of class names to their file paths relative to the plugin root
    // Note: This mapping assumes the files have been renamed to ClassName.php but classes are still AIPS_ClassName

    // We can also use a directory scan or precise mapping.
    // Given the class names usually follow AIPS_Something, we can try to map them dynamically or use a static map.

    $map = [
        // Repositories
        'AIPS_History_Repository' => 'Repositories/HistoryRepository.php',
        'AIPS_Schedule_Repository' => 'Repositories/ScheduleRepository.php',
        'AIPS_Template_Repository' => 'Repositories/TemplateRepository.php',
        'AIPS_Article_Structure_Repository' => 'Repositories/ArticleStructureRepository.php',
        'AIPS_Prompt_Section_Repository' => 'Repositories/PromptSectionRepository.php',
        'AIPS_Trending_Topics_Repository' => 'Repositories/TrendingTopicsRepository.php',

        // Services
        'AIPS_AI_Service' => 'Services/AIService.php',
        'AIPS_Image_Service' => 'Services/ImageService.php',
        'AIPS_Resilience_Service' => 'Services/ResilienceService.php',
        'AIPS_Research_Service' => 'Services/ResearchService.php',
        'AIPS_Generator' => 'Services/Generator.php',
        'AIPS_Post_Creator' => 'Services/PostCreator.php',
        'AIPS_Scheduler' => 'Services/Scheduler.php',
        'AIPS_Article_Structure_Manager' => 'Services/ArticleStructureManager.php',
        'AIPS_Template_Processor' => 'Services/TemplateProcessor.php',
        'AIPS_Template_Type_Selector' => 'Services/TemplateTypeSelector.php',

        // Controllers
        'AIPS_Schedule_Controller' => 'Controllers/ScheduleController.php',
        'AIPS_Research_Controller' => 'Controllers/ResearchController.php',

        // Admin
        'AIPS_History' => 'Admin/History.php',
        'AIPS_Planner' => 'Admin/Planner.php',
        'AIPS_Settings' => 'Admin/Settings.php',
        'AIPS_Templates' => 'Admin/Templates.php',
        'AIPS_Voices' => 'Admin/Voices.php',

        // Core
        'AIPS_Config' => 'Core/Config.php',
        'AIPS_DB_Manager' => 'Core/DBManager.php',
        'AIPS_Logger' => 'Core/Logger.php',
        'AIPS_System_Status' => 'Core/SystemStatus.php',
        'AIPS_Upgrades' => 'Core/Upgrades.php',
        'AIPS_Event_Dispatcher' => 'Core/EventDispatcher.php',
        'AIPS_Generation_Session' => 'Core/GenerationSession.php',

        // Utils
        'AIPS_Interval_Calculator' => 'Utils/IntervalCalculator.php',
    ];

    if (isset($map[$class])) {
        require_once AIPS_PLUGIN_DIR . 'src/' . $map[$class];
    }
});

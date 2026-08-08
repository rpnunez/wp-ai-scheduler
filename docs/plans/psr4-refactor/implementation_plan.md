# PSR-4 Namespace Refactoring Plan

This implementation plan outlines the steps and architecture required to refactor the project from its legacy prefix-based class loading system in `includes/` to a standard PSR-4 namespaced architecture under a new `src/` directory.

## Goals

1. **PSR-4 Compatibility**: Eliminate class prefixing (`class-aips-*`, `interface-aips-*`, `trait-aips-*`) in favor of PSR-4 namespaces mapped to a `src/` directory structure.
2. **Modern Namespace Conventions**: Use namespace `AIPS` as the root, with clean sub-namespaces.
3. **IDE-Friendly Naming**: Retain descriptive suffixes like `Controller`, `Repository`, `Service`, `Interface`, and `Trait` (e.g. `TaxonomyController`, `TaxonomyRepository`) to prevent namespace collisions and simplify IDE class search and imports.
4. **Seamless Transitional Backwards Compatibility**: Support a phased migration using a dynamic class aliasing autoloader so that templates, cron handlers, external hooks, and the 130+ unit test files referencing `AIPS_*` legacy class names do not trigger errors and can be refactored gradually.
5. **No Placeholders / Robust Autoloading**: Update Composer settings and fallback autoloaders to handle the new namespace structure gracefully on all platforms.

---

## Folder & Class Structure Mapping

We are using a **Component-based (Domain) layout** for the `src/` directory. This groups related logic (Controllers, Repositories, Services, Contexts) into cohesive directories rather than having large flat directories.

Here is the updated mapping of existing legacy files to their new `src/` namespaced locations with corrected casing (e.g., `AI`, `UI`, `DB`, `AJAX`, `ID`, `JSON`, `MySQL`, and `Schedule`):

| Legacy File Path (in `includes/`) | New Namespace & Class Name | New File Path (in `src/`) | Status |
| :--- | :--- | :--- | :--- |
| **Core / Common Subsystem** | | | |
| `class-aips-config.php` | `AIPS\Core\Config` | `src/Core/Config.php` | Refactored |
| `class-aips-container.php` | `AIPS\Core\Container` | `src/Core/Container.php` | Refactored |
| `class-aips-date-time.php` | `AIPS\Core\DateTime` | `src/Core/DateTime.php` | Refactored |
| `class-aips-logger.php` | `AIPS\Core\Logger` | `src/Core/Logger.php` | Refactored |
| `interface-aips-logger-interface.php` | `AIPS\Core\LoggerInterface` | `src/Core/LoggerInterface.php` | Refactored |
| `class-aips-correlation-id.php` | `AIPS\Core\CorrelationID` | `src/Core/CorrelationID.php` | Refactored |
| `class-aips-site-context.php` | `AIPS\Core\SiteContext` | `src/Core/SiteContext.php` | Pending |
| `class-aips-utilities.php` | `AIPS\Core\Utilities` | `src/Core/Utilities.php` | Pending |
| `class-aips-error-handler.php` | `AIPS\Core\ErrorHandler` | `src/Core/ErrorHandler.php` | Pending |
| **Database & Migrations** | | | |
| `class-aips-db-manager.php` | `AIPS\DB\DBManager` | `src/DB/DBManager.php` | Pending |
| `class-aips-db-migrations.php` | `AIPS\DB\DBMigrations` | `src/DB/DBMigrations.php` | Pending |
| `class-aips-date-time-db-repair.php` | `AIPS\DB\DateTimeDBRepair` | `src/DB/DateTimeDBRepair.php` | Pending |
| `class-aips-upgrades.php` | `AIPS\DB\Upgrades` | `src/DB/Upgrades.php` | Pending |
| `class-aips-seeder-service.php` | `AIPS\DB\SeederService` | `src/DB/SeederService.php` | Pending |
| **Routing / AJAX** | | | |
| `class-aips-ajax-registry.php` | `AIPS\Routing\AJAXRegistry` | `src/Routing/AJAXRegistry.php` | Refactored |
| `class-aips-ajax-response.php` | `AIPS\Routing\AJAXResponse.php` | `src/Routing/AJAXResponse.php` | Refactored |
| **Cache Subsystem** | | | |
| `class-aips-cache.php` | `AIPS\Cache\Cache` | `src/Cache/Cache.php` | Pending |
| `interface-aips-cache-driver.php` | `AIPS\Cache\CacheDriverInterface` | `src/Cache/CacheDriverInterface.php` | Pending |
| `interface-aips-cache-monitorable-driver.php` | `AIPS\Cache\CacheMonitorableDriverInterface` | `src/Cache/CacheMonitorableDriverInterface.php` | Pending |
| `class-aips-cache-array-driver.php` | `AIPS\Cache\Drivers\ArrayDriver` | `src/Cache/Drivers/ArrayDriver.php` | Pending |
| `class-aips-cache-db-driver.php` | `AIPS\Cache\Drivers\DBDriver` | `src/Cache/Drivers/DBDriver.php` | Pending |
| `class-aips-cache-wp-object-cache-driver.php` | `AIPS\Cache\Drivers\WPObjectCacheDriver` | `src/Cache/Drivers/WPObjectCacheDriver.php` | Pending |
| `class-aips-cache-factory.php` | `AIPS\Cache\CacheFactory` | `src/Cache/CacheFactory.php` | Pending |
| `class-aips-cache-index.php` | `AIPS\Cache\CacheIndex` | `src/Cache/CacheIndex.php` | Pending |
| `class-aips-cache-invalidation-bus.php` | `AIPS\Cache\CacheInvalidationBus` | `src/Cache/CacheInvalidationBus.php` | Pending |
| `class-aips-cache-policy.php` | `AIPS\Cache\CachePolicy` | `src/Cache/CachePolicy.php` | Pending |
| `trait-aips-cacheable-repository.php` | `AIPS\Cache\CacheableRepositoryTrait` | `src/Cache/CacheableRepositoryTrait.php` | Pending |
| `class-aips-repository-cache-config.php` | `AIPS\Cache\RepositoryCacheConfig` | `src/Cache/RepositoryCacheConfig.php` | Pending |
| `class-aips-repository-cache-dependencies.php` | `AIPS\Cache\RepositoryCacheDependencies` | `src/Cache/RepositoryCacheDependencies.php` | Pending |
| `class-aips-repository-cache-key-builder.php` | `AIPS\Cache\RepositoryCacheKeyBuilder` | `src/Cache/RepositoryCacheKeyBuilder.php` | Pending |
| `class-aips-repository-cache-observer.php` | `AIPS\Cache\RepositoryCacheObserver` | `src/Cache/RepositoryCacheObserver.php` | Pending |
| **Campaign Subsystem** | | | |
| `class-aips-campaigns-controller.php` | `AIPS\Campaigns\CampaignsController` | `src/Campaigns/CampaignsController.php` | Refactored |
| `class-aips-campaigns-repository.php` | `AIPS\Campaigns\CampaignsRepository` | `src/Campaigns/CampaignsRepository.php` | Refactored |
| **Taxonomy Subsystem** | | | |
| `class-aips-taxonomy-controller.php` | `AIPS\Taxonomy\TaxonomyController` | `src/Taxonomy/TaxonomyController.php` | Refactored |
| `class-aips-taxonomy-repository.php` | `AIPS\Taxonomy\TaxonomyRepository` | `src/Taxonomy/TaxonomyRepository.php` | Refactored |
| **Template Subsystem** | | | |
| `class-aips-template-context.php` | `AIPS\Templates\TemplateContext` | `src/Templates/TemplateContext.php` | Pending |
| `class-aips-template-data.php` | `AIPS\Templates\TemplateData` | `src/Templates/TemplateData.php` | Pending |
| `class-aips-template-entry.php` | `AIPS\Templates\TemplateEntry` | `src/Templates/TemplateEntry.php` | Pending |
| `class-aips-template-helper.php` | `AIPS\Templates\TemplateHelper` | `src/Templates/TemplateHelper.php` | Pending |
| `class-aips-template-processor.php` | `AIPS\Templates\TemplateProcessor` | `src/Templates/TemplateProcessor.php` | Pending |
| `class-aips-template-repository.php` | `AIPS\Templates\TemplateRepository` | `src/Templates/TemplateRepository.php` | Pending |
| `class-aips-template-type-selector.php` | `AIPS\Templates\TemplateTypeSelector` | `src/Templates/TemplateTypeSelector.php` | Pending |
| `class-aips-templates-controller.php` | `AIPS\Templates\TemplatesController` | `src/Templates/TemplatesController.php` | Pending |
| `class-aips-templates.php` | `AIPS\Templates\Templates` | `src/Templates/Templates.php` | Pending |
| **Scheduler & Jobs** | | | |
| `class-aips-scheduler.php` | `AIPS\Scheduler\Scheduler` | `src/Scheduler/Scheduler.php` | Pending |
| `class-aips-schedule-controller.php` | `AIPS\Scheduler\ScheduleController` | `src/Scheduler/ScheduleController.php` | Pending |
| `class-aips-schedule-entry.php` | `AIPS\Scheduler\ScheduleEntry` | `src/Scheduler/ScheduleEntry.php` | Pending |
| `class-aips-schedule-processor.php` | `AIPS\Scheduler\ScheduleProcessor` | `src/Scheduler/ScheduleProcessor.php` | Pending |
| `class-aips-schedule-repository.php` | `AIPS\Scheduler\ScheduleRepository` | `src/Scheduler/ScheduleRepository.php` | Pending |
| `interface-aips-schedule-repository-interface.php` | `AIPS\Scheduler\ScheduleRepositoryInterface` | `src/Scheduler/ScheduleRepositoryInterface.php` | Pending |
| `class-aips-schedule-result-handler.php` | `AIPS\Scheduler\ScheduleResultHandler` | `src/Scheduler/ScheduleResultHandler.php` | Pending |
| `interface-aips-cron-generation-handler.php` | `AIPS\Scheduler\CronGenerationHandlerInterface` | `src/Scheduler/CronGenerationHandlerInterface.php` | Pending |
| `class-aips-unified-schedule-service.php` | `AIPS\Scheduler\ScheduleService` | `src/Scheduler/ScheduleService.php` | Pending |
| `class-aips-bulk-batch-job-store.php` | `AIPS\Job\BulkBatchJobStore` | `src/Job/BulkBatchJobStore.php` | Pending |
| `class-aips-bulk-batch-processor.php` | `AIPS\Job\BulkBatchProcessor` | `src/Job/BulkBatchProcessor.php` | Pending |
| `class-aips-batch-queue-service.php` | `AIPS\Job\BatchQueueService` | `src/Job/BatchQueueService.php` | Pending |
| `job/class-aips-batch-slicer.php` | `AIPS\Job\BatchSlicer` | `src/Job/BatchSlicer.php` | Pending |
| `job/class-aips-dispatch-summary.php` | `AIPS\Job\DispatchSummary` | `src/Job/DispatchSummary.php` | Pending |
| `job/class-aips-job-definition.php` | `AIPS\Job\JobDefinition` | `src/Job/JobDefinition.php` | Pending |
| `job/class-aips-job-dispatcher.php` | `AIPS\Job\JobDispatcher` | `src/Job/JobDispatcher.php` | Pending |
| `job/class-aips-job-progress-tracker.php` | `AIPS\Job\JobProgressTracker` | `src/Job/JobProgressTracker.php` | Pending |
| `job/class-aips-job-scheduler.php` | `AIPS\Job\JobScheduler` | `src/Job/JobScheduler.php` | Pending |
| `job/class-aips-slice-configuration.php` | `AIPS\Job\SliceConfiguration` | `src/Job/SliceConfiguration.php` | Pending |
| **Author Subsystem** | | | |
| `class-aips-authors-controller.php` | `AIPS\Author\AuthorsController` | `src/Author/AuthorsController.php` | Pending |
| `class-aips-authors-repository.php` | `AIPS\Author\AuthorsRepository` | `src/Author/AuthorsRepository.php` | Pending |
| `class-aips-author-post-generator.php` | `AIPS\Author\AuthorPostGenerator` | `src/Author/AuthorPostGenerator.php` | Pending |
| `class-aips-author-suggestions-service.php` | `AIPS\Author\AuthorSuggestionsService` | `src/Author/AuthorSuggestionsService.php` | Pending |
| `class-aips-author-topic-logs-repository.php` | `AIPS\Author\AuthorTopicLogsRepository` | `src/Author/AuthorTopicLogsRepository.php` | Pending |
| `class-aips-author-topics-controller.php` | `AIPS\Author\AuthorTopicsController` | `src/Author/AuthorTopicsController.php` | Pending |
| `class-aips-author-topics-generator.php` | `AIPS\Author\AuthorTopicsGenerator` | `src/Author/AuthorTopicsGenerator.php` | Pending |
| `class-aips-author-topics-repository.php` | `AIPS\Author\AuthorTopicsRepository` | `src/Author/AuthorTopicsRepository.php` | Pending |
| `class-aips-author-topics-scheduler.php` | `AIPS\Author\AuthorTopicsScheduler` | `src/Author/AuthorTopicsScheduler.php` | Pending |
| `class-aips-author-slice-scheduler-base.php` | `AIPS\Author\AuthorSliceSchedulerBase` | `src/Author/AuthorSliceSchedulerBase.php` | Pending |
| **AI & Generation** | | | |
| `class-aips-ai-service.php` | `AIPS\AI\AIService` | `src/AI/AIService.php` | Refactored |
| `interface-aips-ai-service-interface.php` | `AIPS\AI\AIServiceInterface` | `src/AI/AIServiceInterface.php` | Refactored |
| `class-aips-ai-assistance-controller.php` | `AIPS\AI\AIAssistanceController` | `src/AI/AIAssistanceController.php` | Pending |
| `class-aips-ai-assistance-repository.php` | `AIPS\AI\AIAssistanceRepository` | `src/AI/AIAssistanceRepository.php` | Pending |
| `class-aips-ai-assistance-service.php` | `AIPS\AI\AIAssistanceService` | `src/AI/AIAssistanceService.php` | Pending |
| `class-aips-ai-edit-controller.php` | `AIPS\AI\AIEditController` | `src/AI/AIEditController.php` | Pending |
| `class-aips-generator.php` | `AIPS\AI\Generator` | `src/AI/Generator.php` | Pending |
| `class-aips-generation-context-factory.php` | `AIPS\AI\GenerationContextFactory` | `src/AI/GenerationContextFactory.php` | Pending |
| `interface-aips-generation-context.php` | `AIPS\AI\GenerationContextInterface` | `src/AI/GenerationContextInterface.php` | Pending |
| `class-aips-generation-execution-runner.php` | `AIPS\AI\GenerationExecutionRunner` | `src/AI/GenerationExecutionRunner.php` | Pending |
| `class-aips-generation-logger.php` | `AIPS\AI\GenerationLogger` | `src/AI/GenerationLogger.php` | Pending |
| `class-aips-generation-result.php` | `AIPS\AI\GenerationResult` | `src/AI/GenerationResult.php` | Pending |
| `class-aips-generation-session.php` | `AIPS\AI\GenerationSession` | `src/AI/GenerationSession.php` | Pending |
| `class-aips-bulk-generator-service.php` | `AIPS\AI\BulkGeneratorService` | `src/AI/BulkGeneratorService.php` | Pending |
| `class-aips-partial-generation-state-reconciler.php` | `AIPS\AI\PartialGenerationStateReconciler` | `src/AI/PartialGenerationStateReconciler.php` | Pending |
| `class-aips-token-budget.php` | `AIPS\AI\TokenBudget` | `src/AI/TokenBudget.php` | Pending |
| `class-aips-topic-context.php` | `AIPS\AI\TopicContext` | `src/AI/TopicContext.php` | Pending |
| `class-aips-topic-expansion-service.php` | `AIPS\AI\TopicExpansionService` | `src/AI/TopicExpansionService.php` | Pending |
| `class-aips-topic-penalty-service.php` | `AIPS\AI\TopicPenaltyService` | `src/AI/TopicPenaltyService.php` | Pending |
| **Post Management** | | | |
| `class-aips-post-creator.php` | `AIPS\Posts\PostCreator` | `src/Posts/PostCreator.php` | Pending |
| `class-aips-post-manager.php` | `AIPS\Posts\PostManager` | `src/Posts/PostManager.php` | Pending |
| `class-aips-post-review.php` | `AIPS\Posts\PostReview` | `src/Posts/PostReview.php` | Pending |
| `class-aips-post-review-repository.php` | `AIPS\Posts\PostReviewRepository` | `src/Posts/PostReviewRepository.php` | Pending |
| `class-aips-post-slices-controller.php` | `AIPS\Posts\PostSlicesController` | `src/Posts/PostSlicesController.php` | Pending |
| `class-aips-post-slices-repository.php` | `AIPS\Posts\PostSlicesRepository` | `src/Posts/PostSlicesRepository.php` | Pending |
| `class-aips-generated-posts-controller.php` | `AIPS\Posts\GeneratedPostsController` | `src/Posts/GeneratedPostsController.php` | Pending |
| **History & Logging** | | | |
| `class-aips-history.php` | `AIPS\History\History` | `src/History/History.php` | Pending |
| `class-aips-history-container.php` | `AIPS\History\HistoryContainer` | `src/History/HistoryContainer.php` | Pending |
| `class-aips-history-repository.php` | `AIPS\History\HistoryRepository` | `src/History/HistoryRepository.php` | Pending |
| `interface-aips-history-repository-interface.php` | `AIPS\History\HistoryRepositoryInterface` | `src/History/HistoryRepositoryInterface.php` | Pending |
| `class-aips-history-service.php` | `AIPS\History\HistoryService` | `src/History/HistoryService.php` | Pending |
| `interface-aips-history-service-interface.php` | `AIPS\History\HistoryServiceInterface` | `src/History/HistoryServiceInterface.php` | Pending |
| `class-aips-history-type.php` | `AIPS\History\HistoryType` | `src/History/HistoryType.php` | Pending |
| **Internal Links** | | | |
| `class-aips-internal-links-controller.php` | `AIPS\InternalLinks\InternalLinksController` | `src/InternalLinks/InternalLinksController.php` | Pending |
| `class-aips-internal-links-repository.php` | `AIPS\InternalLinks\InternalLinksRepository` | `src/InternalLinks/InternalLinksRepository.php` | Pending |
| `class-aips-internal-links-service.php` | `AIPS\InternalLinks\InternalLinksService` | `src/InternalLinks/InternalLinksService.php` | Pending |
| `class-aips-internal-link-inserter-service.php` | `AIPS\InternalLinks\InternalLinkInserterService` | `src/InternalLinks/InternalLinkInserterService.php` | Pending |
| **Settings / Menu UI** | | | |
| `class-aips-settings.php` | `AIPS\Settings\Settings` | `src/Settings/Settings.php` | Pending |
| `class-aips-settings-ui.php` | `AIPS\Settings\SettingsUI` | `src/Settings/SettingsUI.php` | Pending |
| `class-aips-settings-ajax.php` | `AIPS\Settings\SettingsAJAX` | `src/Settings/SettingsAJAX.php` | Pending |
| **Notifications** | | | |
| `class-aips-notifications.php` | `AIPS\Notifications\Notifications` | `src/Notifications/Notifications.php` | Pending |
| `class-aips-notifications-repository.php` | `AIPS\Notifications\NotificationsRepository` | `src/Notifications/NotificationsRepository.php` | Pending |
| `interface-aips-notifications-repository-interface.php` | `AIPS\Notifications\NotificationsRepositoryInterface` | `src/Notifications/NotificationsRepositoryInterface.php` | Pending |
| `class-aips-notifications-event-handler.php` | `AIPS\Notifications\NotificationsEventHandler` | `src/Notifications/NotificationsEventHandler.php` | Pending |
| `class-aips-notification-registry.php` | `AIPS\Notifications\NotificationRegistry` | `src/Notifications/NotificationRegistry.php` | Pending |
| `class-aips-notification-senders.php` | `AIPS\Notifications\NotificationSenders` | `src/Notifications/NotificationSenders.php` | Pending |
| `class-aips-notification-template.php` | `AIPS\Notifications\NotificationTemplate` | `src/Notifications/NotificationTemplate.php` | Pending |
| `class-aips-notification-templates.php` | `AIPS\Notifications\NotificationTemplates` | `src/Notifications/NotificationTemplates.php` | Pending |
| **Trusted Sources** | | | |
| `class-aips-sources-repository.php` | `AIPS\Sources\SourcesRepository` | `src/Sources/SourcesRepository.php` | Pending |
| `class-aips-sources-data-repository.php` | `AIPS\Sources\SourcesDataRepository` | `src/Sources/SourcesDataRepository.php` | Pending |
| `class-aips-sources-controller.php` | `AIPS\Sources\SourcesController` | `src/Sources/SourcesController.php` | Pending |
| `class-aips-sources-cron.php` | `AIPS\Sources\SourcesCron` | `src/Sources/SourcesCron.php` | Pending |
| `class-aips-sources-fetcher.php` | `AIPS\Sources\SourcesFetcher` | `src/Sources/SourcesFetcher.php` | Pending |
| **Prompts & Prompt Sections** | | | |
| `class-aips-prompt-builder.php` | `AIPS\Prompts\PromptBuilder` | `src/Prompts/PromptBuilder.php` | Pending |
| `class-aips-prompt-builder-article-structure-section.php` | `AIPS\Prompts\PromptBuilderArticleStructureSection` | `src/Prompts/PromptBuilderArticleStructureSection.php` | Pending |
| `class-aips-prompt-builder-authors.php` | `AIPS\Prompts\PromptBuilderAuthors` | `src/Prompts/PromptBuilderAuthors.php` | Pending |
| `class-aips-prompt-builder-diversity-injector.php` | `AIPS\Prompts\PromptBuilderDiversityInjector` | `src/Prompts/PromptBuilderDiversityInjector.php` | Pending |
| `class-aips-prompt-builder-post-content.php` | `AIPS\Prompts\PromptBuilderPostContent` | `src/Prompts/PromptBuilderPostContent.php` | Pending |
| `class-aips-prompt-builder-post-excerpt.php` | `AIPS\Prompts\PromptBuilderPostExcerpt` | `src/Prompts/PromptBuilderPostExcerpt.php` | Pending |
| `class-aips-prompt-builder-post-featured-image.php` | `AIPS\Prompts\PromptBuilderPostFeaturedImage` | `src/Prompts/PromptBuilderPostFeaturedImage.php` | Pending |
| `class-aips-prompt-builder-post-title.php` | `AIPS\Prompts\PromptBuilderPostTitle` | `src/Prompts/PromptBuilderPostTitle.php` | Pending |
| `class-aips-prompt-builder-taxonomy.php` | `AIPS\Prompts\PromptBuilderTaxonomy` | `src/Prompts/PromptBuilderTaxonomy.php` | Pending |
| `class-aips-prompt-builder-topic.php` | `AIPS\Prompts\PromptBuilderTopic` | `src/Prompts/PromptBuilderTopic.php` | Pending |
| `class-aips-prompt-section-repository.php` | `AIPS\Prompts\PromptSectionRepository` | `src/Prompts/PromptSectionRepository.php` | Pending |
| `class-aips-prompt-sections-controller.php` | `AIPS\Prompts\PromptSectionsController` | `src/Prompts/PromptSectionsController.php` | Pending |
| **Embeddings & Research** | | | |
| `class-aips-embeddings-service.php` | `AIPS\Embeddings\EmbeddingsService` | `src/Embeddings/EmbeddingsService.php` | Pending |
| `class-aips-embeddings-cron.php` | `AIPS\Embeddings\EmbeddingsCron` | `src/Embeddings/EmbeddingsCron.php` | Pending |
| `class-aips-post-embeddings-repository.php` | `AIPS\Embeddings\PostEmbeddingsRepository` | `src/Embeddings/PostEmbeddingsRepository.php` | Pending |
| `class-aips-research-service.php` | `AIPS\Research\ResearchService` | `src/Research/ResearchService.php` | Pending |
| `class-aips-research-controller.php` | `AIPS\Research\ResearchController` | `src/Research/ResearchController.php` | Pending |
| **Cache Monitor Subsystem** | | | |
| `class-aips-cache-monitor-controller.php` | `AIPS\CacheMonitor\CacheMonitorController` | `src/CacheMonitor/CacheMonitorController.php` | Pending |
| `class-aips-cache-monitor-repository.php` | `AIPS\CacheMonitor\CacheMonitorRepository` | `src/CacheMonitor/CacheMonitorRepository.php` | Pending |
| `class-aips-cache-monitor-service.php` | `AIPS\CacheMonitor\CacheMonitorService` | `src/CacheMonitor/CacheMonitorService.php` | Pending |
| **Diagnostics & Telemetry** | | | |
| `class-aips-diagnostics-controller.php` | `AIPS\Diagnostics\DiagnosticsController` | `src/Diagnostics/DiagnosticsController.php` | Pending |
| `class-aips-system-diagnostics-service.php` | `AIPS\Diagnostics\SystemDiagnosticsService` | `src/Diagnostics/SystemDiagnosticsService.php` | Pending |
| `class-aips-system-status-controller.php` | `AIPS\Diagnostics\SystemStatusController` | `src/Diagnostics/SystemStatusController.php` | Pending |
| `class-aips-system-status.php` | `AIPS\Diagnostics\SystemStatus` | `src/Diagnostics/SystemStatus.php` | Pending |
| `class-aips-telemetry.php` | `AIPS\Diagnostics\Telemetry` | `src/Diagnostics/Telemetry.php` | Pending |
| `class-aips-telemetry-controller.php` | `AIPS\Diagnostics\TelemetryController` | `src/Diagnostics/TelemetryController.php` | Pending |
| `class-aips-telemetry-repository.php` | `AIPS\Diagnostics\TelemetryRepository` | `src/Diagnostics/TelemetryRepository.php` | Pending |
| `diagnostics/class-aips-system-diagnostics-environment-provider.php` | `AIPS\Diagnostics\Providers\EnvironmentProvider` | `src/Diagnostics/Providers/EnvironmentProvider.php` | Pending |
| `diagnostics/class-aips-system-diagnostics-logs-provider.php` | `AIPS\Diagnostics\Providers\LogsProvider` | `src/Diagnostics/Providers/LogsProvider.php` | Pending |
| `diagnostics/class-aips-system-diagnostics-queue-provider.php` | `AIPS\Diagnostics\Providers\QueueProvider` | `src/Diagnostics/Providers/QueueProvider.php` | Pending |
| `diagnostics/class-aips-system-diagnostics-scheduler-provider.php` | `AIPS\Diagnostics\Providers\SchedulerProvider` | `src/Diagnostics/Providers/SchedulerProvider.php` | Pending |
| `diagnostics/interface-aips-system-diagnostic-provider-interface.php` | `AIPS\Diagnostics\Providers\SystemDiagnosticProviderInterface` | `src/Diagnostics/Providers/SystemDiagnosticProviderInterface.php` | Pending |
| **Admin & Dashboard UI** | | | |
| `class-aips-admin-assets.php` | `AIPS\Admin\AdminAssets` | `src/Admin/AdminAssets.php` | Pending |
| `class-aips-admin-bar.php` | `AIPS\Admin\AdminBar` | `src/Admin/AdminBar.php` | Pending |
| `class-aips-admin-flow-controller.php` | `AIPS\Admin\AdminFlowController` | `src/Admin/AdminFlowController.php` | Pending |
| `class-aips-admin-menu-helper.php` | `AIPS\Admin\AdminMenuHelper` | `src/Admin/AdminMenuHelper.php` | Pending |
| `class-aips-admin-menu.php` | `AIPS\Admin\AdminMenu` | `src/Admin/AdminMenu.php` | Pending |
| `class-aips-dashboard-controller.php` | `AIPS\Admin\DashboardController` | `src/Admin/DashboardController.php` | Pending |
| `class-aips-onboarding-wizard.php` | `AIPS\Admin\OnboardingWizard` | `src/Admin/OnboardingWizard.php` | Pending |
| `class-aips-post-history-ui.php` | `AIPS\Admin\PostHistoryUI` | `src/Admin/PostHistoryUI.php` | Pending |
| `class-aips-seeder-admin.php` | `AIPS\Admin\SeederAdmin` | `src/Admin/SeederAdmin.php` | Pending |
| **Other Services** | | | |
| `class-aips-article-structure-manager.php` | `AIPS\Services\ArticleStructureManager` | `src/Services/ArticleStructureManager.php` | Pending |
| `class-aips-article-structure-repository.php` | `AIPS\Services\ArticleStructureRepository` | `src/Services/ArticleStructureRepository.php` | Pending |
| `class-aips-content-auditor.php` | `AIPS\Services\ContentAuditor` | `src/Services/ContentAuditor.php` | Pending |
| `class-aips-dev-tools.php` | `AIPS\Services\DevTools` | `src/Services/DevTools.php` | Pending |
| `class-aips-image-service.php` | `AIPS\Services\ImageService` | `src/Services/ImageService.php` | Pending |
| `class-aips-markdown-parser.php` | `AIPS\Services\MarkdownParser` | `src/Services/MarkdownParser.php` | Pending |
| `class-aips-metrics-repository.php` | `AIPS\Services\MetricsRepository` | `src/Services/MetricsRepository.php` | Pending |
| `class-aips-operations-insights-controller.php` | `AIPS\Services\OperationsInsightsController` | `src/Services/OperationsInsightsController.php` | Pending |
| `class-aips-planner.php` | `AIPS\Services\Planner` | `src/Services/Planner.php` | Pending |
| `class-aips-resilience-service.php` | `AIPS\Services\ResilienceService` | `src/Services/ResilienceService.php` | Pending |
| `class-aips-component-regeneration-service.php` | `AIPS\Services\ComponentRegenerationService` | `src/Services/ComponentRegenerationService.php` | Pending |
| `class-aips-trending-topics-repository.php` | `AIPS\Services\TrendingTopicsRepository` | `src/Services/TrendingTopicsRepository.php` | Pending |
| `class-aips-voices-repository.php` | `AIPS\Services\VoicesRepository` | `src/Services/VoicesRepository.php` | Pending |
| `class-aips-voices.php` | `AIPS\Services\Voices` | `src/Services/Voices.php` | Pending |
| `class-aips-session-to-json.php` | `AIPS\Services\SessionToJSON` | `src/Services/SessionToJSON.php` | Pending |
| `class-aips-structures-controller.php` | `AIPS\Services\StructuresController` | `src/Services/StructuresController.php` | Pending |
| **Data Management** | | | |
| `class-aips-data-management.php` | `AIPS\DataManagement\DataManagement` | `src/DataManagement/DataManagement.php` | Pending |
| `class-aips-data-management-repository.php` | `AIPS\DataManagement\DataManagementRepository` | `src/DataManagement/DataManagementRepository.php` | Pending |
| `class-aips-data-management-export.php` | `AIPS\DataManagement\DataManagementExport` | `src/DataManagement/DataManagementExport.php` | Pending |
| `class-aips-data-management-export-json.php` | `AIPS\DataManagement\Export\ExportJSON` | `src/DataManagement/Export/ExportJSON.php` | Pending |
| `class-aips-data-management-export-mysql.php` | `AIPS\DataManagement\Export\ExportMySQL` | `src/DataManagement/Export/ExportMySQL.php` | Pending |
| `class-aips-data-management-import.php` | `AIPS\DataManagement\DataManagementImport` | `src/DataManagement/DataManagementImport.php` | Pending |
| `class-aips-data-management-import-json.php` | `AIPS\DataManagement\Import\ImportJSON` | `src/DataManagement/Import/ImportJSON.php` | Pending |
| `class-aips-data-management-import-mysql.php` | `AIPS\DataManagement\Import\ImportMySQL` | `src/DataManagement/Import/ImportMySQL.php` | Pending |

---

## User Review Required

We have identified several critical architecture points requiring design decisions or considerations:

> [!IMPORTANT]
> **Autoloading and Fallback Shim**: 
> WordPress plugins are often distributed zip-packed without a vendor directory. To ensure the plugin works seamlessly under both local development (Composer-enabled) and production installs, we must maintain the `AIPS_Autoloader` fallback.
> We propose updating `AIPS_Autoloader` to translate namespaced calls like `AIPS\Campaigns\CampaignsController` to `src/Campaigns/CampaignsController.php` using standard PSR-4 naming rules.

> [!WARNING]
> **Transitional Class Aliasing Strategy**:
> Rather than renaming references in all templates, database serializations, and the 130+ unit test files at once, we will use a **Dynamic Class Aliasing** autoloader. 
> When a legacy class name like `AIPS_Taxonomy_Repository` is triggered, the class aliasing system will dynamically load the new namespaced class `\AIPS\Taxonomy\TaxonomyRepository` and register a `class_alias()`. This isolates the code refactoring to the `/includes/` directory and minimizes the risk of breaking hooks, templates, or tests.

---

## Proposed Changes

### 1. [MODIFY] `composer.json`
Update the Composer autoloading structure to register the new `AIPS` namespace.
```diff
   "autoload": {
-    "classmap": [
-      "includes/"
-    ]
+    "psr-4": {
+      "AIPS\\": "src/"
+    }
   },
```

### 2. [NEW] Autoloader Shim (`ai-post-scheduler/src/Core/Autoloader.php`)
Replace the legacy autoloader with a modern PSR-4 shim that handles name translation and class aliasing.
```php
<?php
namespace AIPS\Core;

if (!defined('ABSPATH')) {
	exit;
}

class Autoloader {
	private static $aliases = array(
		'AIPS_Config'                 => \AIPS\Core\Config::class,
		'AIPS_Container'              => \AIPS\Core\Container::class,
		'AIPS_Logger'                 => \AIPS\Core\Logger::class,
		'AIPS_Logger_Interface'       => \AIPS\Core\LoggerInterface::class,
		'AIPS_DateTime'               => \AIPS\Core\DateTime::class,
		'AIPS_Telemetry'              => \AIPS\Diagnostics\Telemetry::class,
		'AIPS_Taxonomy_Controller'    => \AIPS\Taxonomy\TaxonomyController::class,
		'AIPS_Taxonomy_Repository'    => \AIPS\Taxonomy\TaxonomyRepository::class,
		'AIPS_Campaigns_Controller'   => \AIPS\Campaigns\CampaignsController::class,
		'AIPS_Campaigns_Repository'   => \AIPS\Campaigns\CampaignsRepository::class,
		'AIPS_AI_Service'             => \AIPS\AI\AIService::class,
		'AIPS_AI_Service_Interface'   => \AIPS\AI\AIServiceInterface::class,
		'AIPS_Post_History_Ui'        => \AIPS\Admin\PostHistoryUI::class,
		'AIPS_Unified_Schedule_Service' => \AIPS\Scheduler\ScheduleService::class,
		// ... complete legacy class maps for transparent aliasing ...
	);

	public static function register() {
		// Register namespaced PSR-4 autoloader
		spl_autoload_register(array(__CLASS__, 'load_psr4'));
		// Register legacy alias autoloader (last priority)
		spl_autoload_register(array(__CLASS__, 'load_alias'), true, true);
	}

	public static function load_psr4($class_name) {
		if (strpos($class_name, 'AIPS\\') !== 0) {
			return;
		}

		$relative_class = substr($class_name, 5);
		$file = AIPS_PLUGIN_DIR . 'src/' . str_replace('\\', '/', $relative_class) . '.php';

		if (file_exists($file)) {
			require_once $file;
		}
	}

	public static function load_alias($class_name) {
		if (isset(self::$aliases[$class_name])) {
			$target = self::$aliases[$class_name];
			if (!class_exists($target) && !interface_exists($target) && !trait_exists($target)) {
				self::load_psr4($target);
			}
			class_alias($target, $class_name);
		}
	}
}
```

### 3. [MODIFY] `ai-post-scheduler.php`
Update the bootstrap file to load the new autoloader and use namespaced class references:
```diff
-        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-autoloader.php';
-        AIPS_Autoloader::register();
+        require_once AIPS_PLUGIN_DIR . 'src/Core/Autoloader.php';
+        \AIPS\Core\Autoloader::register();
```

---

## Verification Plan

Refactoring 150+ classes is a high-risk operation. We will verify correctness through systematic automated and manual processes.

### Automated Tests
1. **Composer Dump-Autoload**: Run `composer dump-autoload` to re-generate class maps.
2. **PHP Syntax Linter**: Run PHP syntax checks on all files in `src/` to ensure namespace and PHP changes are correct.
   - `find src/ -name "*.php" -exec php -l {} \;` (or PowerShell equivalent).
3. **Focused Tests Execution**: Run PHPUnit suite after refactoring the autoloader. The existing tests in `tests/` target the legacy classes, which will validate that the class aliasing system works correctly under all test environments.

### Manual Verification
1. **WordPress Backend Load**: Activate/Deactivate the plugin in a local environment. Ensure no PHP warnings, notices, or fatal errors are thrown.
2. **AJAX Action Triggering**: Test standard AJAX endpoints (e.g. Taxonomy loading, Campaign editing) from the UI to verify `AIPS_Ajax_Registry` and namespaced controllers resolve, authenticate, and reply correctly.
3. **Cron Verification**: Check if cron schedules continue working and can lazy-resolve components using namespaces.

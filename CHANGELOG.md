# Changelog

## [Unreleased]

## [1.0.0] - 2026-05-12

- Initial public release prepared for GitHub and WordPress.org.
- Included UTM Tracker, Toolkit, and Close Campaign modules.
- Added shared Leyka Boost settings and unified event log.
- Added Russian and English localization files.
- Added public plugin assets for WordPress.org listing.

## [0.0.4] - 2026-05-10

- [i18n] Добавлены русские переводы всех строк модуля UTM Tracker в `leyka-boost-ru_RU.po`.
- [admin-ui] Исправлен подсчёт статистики в карточках журнала событий — ERROR/INFO/DEBUG теперь считаются по всему файлу, не по срезу 500 строк.
- [close-campaign] Удалены мёртвые настройки логирования (`logging_enabled`, `log_level`, `log_retention_days`, `console_logging`) из настроек модуля — логирование управляется глобально.
- [core] Версия поднята до 0.0.4.

## [0.0.3] - 2026-05-10

- [i18n] Исправлен перевод: «Попыток» → «Попытка» в карточке статистики UTM Tracker.
- [utm-tracker] Добавлена пагинация в таблицу «Все пожертвования» (25 записей на страницу).
- [utm-tracker] Добавлена колонка порядкового номера «#» в таблицу «Все пожертвования».
- [admin-ui] Исправлено дублирование пункта «Закрыть сбор» и подпункта «Настройки» в меню.
- [core] Версия поднята до 0.0.3.

## [0.0.2] - 2026-05-10

- [admin-ui] Реализована единая страница журнала событий с карточками статистики, таблицей, фильтрами и пагинацией.
- [admin-ui] Исправлена структура меню — удалены дублирующие подпункты журналов модулей.
- [admin-ui] Модуль Close Campaign отображается в UI как «Закрыть сбор».
- [close-campaign] Удалён `Leyka_Close_Logger`, логирование переведено на общий `LeykaBoost_Logger`.
- [core] Версия поднята до 0.0.2.
- [i18n] Пункты меню добавлены в `leyka-boost-ru_RU.po` и `leyka-boost-en_US.po`, скомпилированы `.mo`.
- [core] Added initial `leyka-boost/` plugin scaffold.
- [core] Initialized required project documentation files.
- [core] Added plugin bootstrap, dependency checks, default settings, textdomain loading, module loading, and admin notices.
- [admin-ui] Fixed Leyka Boost top-level admin menu not registering because menu initialization happened too late.
- [close-campaign] Fixed unified log file metadata keys used by Close Campaign logging.
- [logger] Added unified file logger with hash-based log file and retention rotation.
- [admin-ui] Added unified Leyka Boost admin menu, settings page, Log Page, log filters, AJAX log clearing, and log download.
- [admin-ui] Aligned shared Settings and Log Page labels, empty state, confirmation text, cards, and badges with `UI-GUIDELINES.md`.
- [admin-ui] Aligned UTM Tracker, Toolkit, and Close Campaign admin UI terminology and destructive action confirmations with `UI-GUIDELINES.md`.
- [core] Added Russian and English translation files and moved admin UI source strings to English fallback textdomain entries.
- [close-campaign] Ported Close Campaign module with legacy settings compatibility and unified logging.
- [toolkit] Ported Toolkit module with legacy settings compatibility and unified logging.
- [utm-tracker] Ported UTM Tracker module with legacy table/options compatibility, admin pages, CSV export, frontend capture script, and generator assets.
- [core] Added uninstall cleanup for Leyka Boost options, module options, UTM Tracker table, and log files.

## [0.0.1] - YYYY-MM-DD

- Initial release.

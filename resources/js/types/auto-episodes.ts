import { SharedData } from '.';

export type MonitoringSchedulePreset = 'hourly' | 'daily' | 'weekly';

export type MonitoringEventFilter =
    | 'all'
    | Extract<App.Enums.AutoEpisodes.SeriesMonitorEventType, 'queued' | 'duplicate' | 'deferred' | 'error'>;

export interface MonitoringSettingsPageProps extends SharedData, App.Data.AutoEpisodes.MonitoringPageData {}

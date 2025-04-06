import { SharedData } from '.';
import Pagination from './pagination';

export interface DownloadsPageProps extends SharedData {
    downloads: Pagination<App.Data.MediaDownloadRefData>;
}

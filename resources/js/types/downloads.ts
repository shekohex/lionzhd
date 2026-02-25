import { SharedData } from '.';
import Pagination from './pagination';

export interface DownloadOwnerOption {
    id: number;
    name: string;
    email: string;
}

export interface DownloadsPageProps extends SharedData {
    downloads: Pagination<App.Data.MediaDownloadRefData>;
    ownerOptions?: DownloadOwnerOption[];
}

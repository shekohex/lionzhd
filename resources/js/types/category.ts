export interface Category {
    id: number;
    name: string;
    parent_id: number;
    type: 'movie' | 'series';
}

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import styles from './SortingFilter.module.scss';
import type { SortByType, SecondarySortByType, TimeFilterType } from '../../../types/common';

interface SortingFilterProps {
    sortBy: SortByType;
    secondarySortBy: SecondarySortByType;
    timeFilter: TimeFilterType;
    onSortChange: (value: SortByType) => void;
    onSecondarySortChange: (value: SecondarySortByType) => void;
    onTimeFilterChange: (value: TimeFilterType) => void;
}

export const SortingFilter = ({
    sortBy,
    secondarySortBy,
    timeFilter,
    onSortChange,
    onSecondarySortChange,
    onTimeFilterChange
}: SortingFilterProps) => {
    const { t } = useTranslation('category');
    const [open, setOpen] = useState(false);

    const handleReset = () => {
        onSortChange('newest');
        onSecondarySortChange('none');
        onTimeFilterChange('all');
    };

    return (
        <div className={styles.sort_filter_block}>
            <button className={styles.toggle_btn} onClick={() => setOpen(v => !v)} type="button">
                <span>{t('sorting.title')}</span>
                <span className={styles.toggle_icon}>{open ? '▽' : '▷'}</span>
            </button>
            {open && (
                <div className={styles.sort_filter_items}>
            <div className={styles.sort_filter_item}>
                <label htmlFor="sortBy">{t('sorting.primarySort')}</label>
                <select
                    id="sortBy"
                    value={sortBy}
                    onChange={(e) => onSortChange(e.target.value as SortByType)}
                    className={styles.select}
                >
                    <option value="newest">{t('sorting.newest')}</option>
                    <option value="oldest">{t('sorting.oldest')}</option>
                    <option value="price-asc">{t('sorting.priceAsc')}</option>
                    <option value="price-desc">{t('sorting.priceDesc')}</option>
                    <option value="reviews-asc">{t('sorting.reviewsAsc')}</option>
                    <option value="reviews-desc">{t('sorting.reviewsDesc')}</option>
                    <option value="rating-asc">{t('sorting.ratingAsc')}</option>
                    <option value="rating-desc">{t('sorting.ratingDesc')}</option>
                </select>
            </div>

            <div className={styles.sort_filter_item}>
                <label htmlFor="secondarySortBy">{t('sorting.secondarySort')}</label>
                <select
                    id="secondarySortBy"
                    value={secondarySortBy}
                    onChange={(e) => onSecondarySortChange(e.target.value as SecondarySortByType)}
                    className={styles.select}
                >
                    <option value="none">{t('sorting.none')}</option>
                    <option value="newest">{t('sorting.newest')}</option>
                    <option value="oldest">{t('sorting.oldest')}</option>
                    <option value="price-asc">{t('sorting.priceAsc')}</option>
                    <option value="price-desc">{t('sorting.priceDesc')}</option>
                    <option value="reviews-asc">{t('sorting.reviewsAsc')}</option>
                    <option value="reviews-desc">{t('sorting.reviewsDesc')}</option>
                    <option value="rating-asc">{t('sorting.ratingAsc')}</option>
                    <option value="rating-desc">{t('sorting.ratingDesc')}</option>
                </select>
            </div>

            <div className={styles.sort_filter_item}>
                <label htmlFor="timeFilter">{t('sorting.timePeriod')}</label>
                <select
                    id="timeFilter"
                    value={timeFilter}
                    onChange={(e) => onTimeFilterChange(e.target.value as TimeFilterType)}
                    className={styles.select}
                >
                    <option value="all">{t('sorting.all')}</option>
                    <option value="today">{t('sorting.today')}</option>
                    <option value="yesterday">{t('sorting.yesterday')}</option>
                    <option value="week">{t('sorting.week')}</option>
                    <option value="month">{t('sorting.month')}</option>
                </select>
            </div>

            <div className={styles.sort_filter_footer}>
                <button type="button" className={styles.reset_btn} onClick={handleReset}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M1 4V10H7" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        <path d="M3.51 15C4.15839 16.8404 5.38734 18.4202 7.01166 19.5014C8.63598 20.5826 10.5677 21.1066 12.5157 20.9945C14.4637 20.8824 16.3226 20.1402 17.8121 18.8798C19.3017 17.6193 20.3413 15.909 20.7742 14.0064C21.2072 12.1037 21.0101 10.112 20.2126 8.33111C19.4152 6.55025 18.0605 5.07686 16.3528 4.13077C14.6451 3.18469 12.6769 2.81662 10.7447 3.08098C8.81245 3.34534 7.02091 4.22637 5.64 5.59L1 10" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                    {t('sorting.reset')}
                </button>
            </div>
            </div>
            )}
        </div>
    );
};

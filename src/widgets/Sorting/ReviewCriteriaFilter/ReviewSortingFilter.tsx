import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import styles from '../CriteriaFilter/SortingFilter.module.scss';
import { Toggle } from '../../../shared/ui/Button/Toggle/Toggle';
import type { ReviewSortByType, ReviewTimeFilterType } from '../../../types/common';
export type { ReviewSortByType, ReviewTimeFilterType };

interface ReviewSortingFilterProps {
    sortBy: ReviewSortByType;
    timeFilter: ReviewTimeFilterType;
    withPhotosOnly: boolean;
    onSortChange: (value: ReviewSortByType) => void;
    onTimeFilterChange: (value: ReviewTimeFilterType) => void;
    onWithPhotosToggle: () => void;
}

export const ReviewSortingFilter = ({
    sortBy,
    timeFilter,
    withPhotosOnly,
    onSortChange,
    onTimeFilterChange,
    onWithPhotosToggle,
}: ReviewSortingFilterProps) => {
    const { t } = useTranslation('profile');
    const [open, setOpen] = useState(false);

    const handleReset = () => {
        onSortChange('newest');
        onTimeFilterChange('all');
        if (withPhotosOnly) onWithPhotosToggle();
    };

    return (
        <div className={styles.sort_filter_block}>
            <button className={styles.toggle_btn} onClick={() => setOpen(v => !v)} type="button">
                <span>{t('reviewSorting.title')}</span>
                <span className={styles.toggle_icon}>{open ? '▽' : '▷'}</span>
            </button>
            {open && (
                <div className={styles.sort_filter_items}>
            <div className={styles.sort_filter_item} style={{ flex: '45 1 0' }}>
                <label htmlFor="reviewSortBy">{t('reviewSorting.sort')}</label>
                <select
                    id="reviewSortBy"
                    value={sortBy}
                    onChange={(e) => onSortChange(e.target.value as ReviewSortByType)}
                    className={styles.select}
                >
                    <option value="newest">{t('reviewSorting.newest')}</option>
                    <option value="oldest">{t('reviewSorting.oldest')}</option>
                    <option value="rating-high">{t('reviewSorting.ratingHigh')}</option>
                    <option value="rating-low">{t('reviewSorting.ratingLow')}</option>
                </select>
            </div>

            <div className={styles.sort_filter_item} style={{ flex: '45 1 0' }}>
                <label htmlFor="reviewTimeFilter">{t('reviewSorting.timePeriod')}</label>
                <select
                    id="reviewTimeFilter"
                    value={timeFilter}
                    onChange={(e) => onTimeFilterChange(e.target.value as ReviewTimeFilterType)}
                    className={styles.select}
                >
                    <option value="all">{t('reviewSorting.all')}</option>
                    <option value="today">{t('reviewSorting.today')}</option>
                    <option value="yesterday">{t('reviewSorting.yesterday')}</option>
                    <option value="week">{t('reviewSorting.week')}</option>
                    <option value="month">{t('reviewSorting.month')}</option>
                </select>
            </div>

            <div className={styles.sort_filter_item} style={{ flex: '10 1 0', minWidth: 'unset' }}>
                <label>{t('reviewSorting.withPhotos')}</label>
                <div style={{ display: 'flex', alignItems: 'center', height: '40px' }}>
                    <Toggle checked={withPhotosOnly} onChange={onWithPhotosToggle} />
                </div>
            </div>

            <div className={styles.sort_filter_footer}>
                <button type="button" className={styles.reset_btn} onClick={handleReset}>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M1 4V10H7" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        <path d="M3.51 15C4.15839 16.8404 5.38734 18.4202 7.01166 19.5014C8.63598 20.5826 10.5677 21.1066 12.5157 20.9945C14.4637 20.8824 16.3226 20.1402 17.8121 18.8798C19.3017 17.6193 20.3413 15.909 20.7742 14.0064C21.2072 12.1037 21.0101 10.112 20.2126 8.33111C19.4152 6.55025 18.0605 5.07686 16.3528 4.13077C14.6451 3.18469 12.6769 2.81662 10.7447 3.08098C8.81245 3.34534 7.02091 4.22637 5.64 5.59L1 10" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                    {t('reviewSorting.reset')}
                </button>
            </div>
            </div>
            )}
        </div>
    );
};

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import styles from '../CriteriaFilter/SortingFilter.module.scss';
import type { TechSupportSortOrder } from '../../../types/common';

export interface TechSupportFilterOption {
    value: string;
    label: string;
}

interface TechSupportSortingFilterProps {
    sortOrder: TechSupportSortOrder;
    filterReason: string;
    filterStatus: string;
    filterPriority: string;
    reasonOptions: TechSupportFilterOption[];
    statusOptions: TechSupportFilterOption[];
    priorityOptions: TechSupportFilterOption[];
    onSortChange: (value: TechSupportSortOrder) => void;
    onReasonChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onPriorityChange: (value: string) => void;
}

/**
 * Collapsible sort/filter panel for the Tech Support "my tickets" table.
 * Same chrome as widgets/Sorting/CriteriaFilter/SortingFilter (shares its CSS module) —
 * a separate component because the criteria are domain-specific (reason/status/priority
 * instead of price/rating/reviews) and don't fit SortingFilter's fixed field set.
 */
export const TechSupportSortingFilter = ({
    sortOrder,
    filterReason,
    filterStatus,
    filterPriority,
    reasonOptions,
    statusOptions,
    priorityOptions,
    onSortChange,
    onReasonChange,
    onStatusChange,
    onPriorityChange,
}: TechSupportSortingFilterProps) => {
    const { t } = useTranslation('techSupport');
    const [open, setOpen] = useState(false);

    const handleReset = () => {
        onSortChange('newest');
        onReasonChange('');
        onStatusChange('');
        onPriorityChange('');
    };

    return (
        <div className={styles.sort_filter_block}>
            <button className={styles.toggle_btn} onClick={() => setOpen(v => !v)} type="button">
                <span>{t('myTickets.filters.toggle')}</span>
                <span className={styles.toggle_icon}>{open ? '▽' : '▷'}</span>
            </button>
            {open && (
                <div className={styles.sort_filter_items}>
                    <div className={styles.sort_filter_item}>
                        <label htmlFor="ts-sort">{t('myTickets.filters.sort')}</label>
                        <select
                            id="ts-sort"
                            value={sortOrder}
                            onChange={e => onSortChange(e.target.value as TechSupportSortOrder)}
                            className={styles.select}
                        >
                            <option value="newest">{t('myTickets.filters.sortNewest')}</option>
                            <option value="oldest">{t('myTickets.filters.sortOldest')}</option>
                        </select>
                    </div>

                    <div className={styles.sort_filter_item}>
                        <label htmlFor="ts-category">{t('myTickets.filters.category')}</label>
                        <select
                            id="ts-category"
                            value={filterReason}
                            onChange={e => onReasonChange(e.target.value)}
                            className={styles.select}
                        >
                            {reasonOptions.map(opt => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>

                    <div className={styles.sort_filter_item}>
                        <label htmlFor="ts-status">{t('myTickets.filters.status')}</label>
                        <select
                            id="ts-status"
                            value={filterStatus}
                            onChange={e => onStatusChange(e.target.value)}
                            className={styles.select}
                        >
                            {statusOptions.map(opt => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>

                    <div className={styles.sort_filter_item}>
                        <label htmlFor="ts-priority">{t('myTickets.filters.priority')}</label>
                        <select
                            id="ts-priority"
                            value={filterPriority}
                            onChange={e => onPriorityChange(e.target.value)}
                            className={styles.select}
                        >
                            {priorityOptions.map(opt => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>

                    <div className={styles.sort_filter_footer}>
                        <button type="button" className={styles.reset_btn} onClick={handleReset}>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 4V10H7" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                                <path d="M3.51 15C4.15839 16.8404 5.38734 18.4202 7.01166 19.5014C8.63598 20.5826 10.5677 21.1066 12.5157 20.9945C14.4637 20.8824 16.3226 20.1402 17.8121 18.8798C19.3017 17.6193 20.3413 15.909 20.7742 14.0064C21.2072 12.1037 21.0101 10.112 20.2126 8.33111C19.4152 6.55025 18.0605 5.07686 16.3528 4.13077C14.6451 3.18469 12.6769 2.81662 10.7447 3.08098C8.81245 3.34534 7.02091 4.22637 5.64 5.59L1 10" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                            </svg>
                            {t('myTickets.resetFilters')}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};

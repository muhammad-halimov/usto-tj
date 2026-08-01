import { useState, useEffect, useRef } from 'react';
import type * as React from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import styles from './TechSupport.module.scss';
import { universalApiRequest } from '../../utils/apiUtils';
import { uploadPhotos } from '../../utils/imageUtils';
import { isAuthenticated } from '../../utils/authUtils';
import { SelectSearch } from '../../shared/ui/SelectSearch';
import Grid, { type PhotoItem } from '../../shared/ui/Photo/Grid';
import { Preview, usePreview } from '../../shared/ui/Photo/Preview';
import Status from '../../shared/ui/Modal/Status';
import { EmptyState } from '../../widgets/EmptyState';
import { formatTicketImageUrl } from '../../utils/imageUtils';
import { EditActions } from '../profile/shared/ui/EditActions/EditActions';
import { Tabs } from '../../shared/ui/Tabs';
import { IoPencilOutline, IoListOutline } from 'react-icons/io5';

interface AppealReason {
    id: number;
    title: string;
}

interface SupportTicket {
    id: number;
    title: string;
    description: string;
    priority: string;
    status?: string;
    createdAt?: string;
    reason?: { title?: string };
    /** Present only in the POST /tech-supports response for guest (unauthenticated) tickets. */
    guestAccessToken?: string;
}

type SupportTab = 'create' | 'my';

const PRIORITY_KEYS = ['1', '2', '3', '4'] as const;

export interface TechSupportProps {
    /** When true, skips the outer container padding and page title (used when embedded in tabs). */
    embedded?: boolean;
}

function TechSupport({ embedded = false }: TechSupportProps) {
    const { t } = useTranslation('techSupport');
    const navigate = useNavigate();
    const isAuth = isAuthenticated();
    const formRef = useRef<HTMLFormElement>(null);

    const [activeTab, setActiveTab] = useState<SupportTab>('create');

    // My tickets state
    const [myTickets, setMyTickets] = useState<SupportTicket[]>([]);
    const [loadingMyTickets, setLoadingMyTickets] = useState(false);
    const [myTicketsError, setMyTicketsError] = useState('');

    // Form fields
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [reasonIri, setReasonIri] = useState('');
    const [priority, setPriority] = useState('');
    const [guestEmail, setGuestEmail] = useState('');
    const [photos, setPhotos] = useState<PhotoItem[]>([]);

    // UI state
    const [reasons, setReasons] = useState<AppealReason[]>([]);
    const [loadingReasons, setLoadingReasons] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showSuccess, setShowSuccess] = useState(false);
    const [showError, setShowError] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');
    const [warningMessage, setWarningMessage] = useState('');
    const [showWarning, setShowWarning] = useState(false);

    // Photo gallery — blob URLs for newly added photos (same pattern as CreateEdit for existing photos)
    const photoUrls = photos
        .filter((p): p is Extract<PhotoItem, { type: 'new' }> => p.type === 'new')
        .map(p => p.previewUrl);
    const gallery = usePreview({ images: photoUrls });

    // Load appeal reasons
    useEffect(() => {
        const fetchReasons = async () => {
            try {
                setLoadingReasons(true);
                const data = await universalApiRequest(
                    `/api/appeal-reasons?applicableTo=support`,
                );
                const list: AppealReason[] = Array.isArray(data)
                    ? data
                    : data?.['hydra:member'] ?? [];
                setReasons(list);
            } catch {
                setErrorMessage(t('loadError'));
                setShowError(true);
            } finally {
                setLoadingReasons(false);
            }
        };
        fetchReasons();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (activeTab !== 'my' || !isAuth) return;
        const fetch = async () => {
            try {
                setLoadingMyTickets(true);
                setMyTicketsError('');
                const data = await universalApiRequest('/api/tech-supports/me');
                const list: SupportTicket[] = Array.isArray(data)
                    ? data
                    : data?.['hydra:member'] ?? [];
                setMyTickets(list);
            } catch {
                setMyTicketsError(t('myTickets.loadError'));
            } finally {
                setLoadingMyTickets(false);
            }
        };
        fetch();
    }, [activeTab, isAuth]); // eslint-disable-line react-hooks/exhaustive-deps

    const priorityOptions = PRIORITY_KEYS.map(k => ({
        value: k,
        label: t(`priority.${k}`),
    }));

    const reasonOptions = reasons.map(r => ({
        value: `/api/appeal-reasons/${r.id}`,
        label: r.title,
    }));

    const openWarning = (msg: string) => {
        setWarningMessage(msg);
        setShowWarning(true);
    };

    const resetForm = () => {
        setTitle('');
        setDescription('');
        setReasonIri('');
        setPriority('');
        setGuestEmail('');
        setPhotos([]);
    };

    const submitForm = async () => {
        if (!title.trim()) { openWarning(t('validation.titleRequired')); return; }
        if (!description.trim()) { openWarning(t('validation.descriptionRequired')); return; }
        if (!reasonIri) { openWarning(t('validation.reasonRequired')); return; }
        if (!priority) { openWarning(t('validation.priorityRequired')); return; }
        if (!isAuth) {
            if (!guestEmail.trim()) { openWarning(t('validation.emailRequired')); return; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail.trim())) {
                openWarning(t('validation.emailInvalid'));
                return;
            }
        }

        try {
            setIsSubmitting(true);

            const payload: Record<string, string> = {
                title: title.trim(),
                description: description.trim(),
                reason: reasonIri,
                priority,
            };
            if (!isAuth && guestEmail.trim()) {
                payload.guestEmail = guestEmail.trim();
            }

            const result: SupportTicket = await universalApiRequest('/api/tech-supports', {
                method: 'POST',
                body: payload,
            });

            // Upload photos if any.
            // For guests, the server returns a one-time guestAccessToken in the
            // create response — it must be sent with each upload to prove ownership
            // of this specific ticket (anonymous requests can't rely on JWT auth).
            const newFiles = photos
                .filter((p): p is Extract<PhotoItem, { type: 'new' }> => p.type === 'new')
                .map(p => p.file);

            if (newFiles.length > 0 && result?.id) {
                const guestToken = !isAuth ? result.guestAccessToken : undefined;

                try {
                    await uploadPhotos('tech-supports', result.id, newFiles, guestToken);
                } catch {
                    // Photo upload failures are non-critical
                }
            }

            setShowSuccess(true);
            resetForm();
        } catch (err) {
            setErrorMessage(err instanceof Error ? err.message : t('error'));
            setShowError(true);
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submitForm();
    };

    const supportTabs = [
        { key: 'create' as SupportTab, icon: <IoPencilOutline />, label: t('tabs.create') },
        ...(isAuth ? [{ key: 'my' as SupportTab, icon: <IoListOutline />, label: t('tabs.my') }] : []),
    ];

    return (
        <div className={embedded ? styles.embeddedContainer : styles.container}>
            {!embedded && (
                <div className={styles.header}>
                    <h1 className={styles.title}>{t('pageTitle')}</h1>
                    <p className={styles.subtitle}>{t('pageSubtitle')}</p>
                </div>
            )}

            <div className={styles.tabsWrapper}>
                <Tabs tabs={supportTabs} activeTab={activeTab} onChange={setActiveTab} />
            </div>

            {activeTab === 'my' ? (
                <div className={styles.myTickets}>
                    {loadingMyTickets ? (
                        <EmptyState isLoading />
                    ) : myTicketsError ? (
                        <EmptyState title={myTicketsError} onRefresh={() => { setMyTickets([]); setActiveTab('create'); setActiveTab('my'); }} />
                    ) : myTickets.length === 0 ? (
                        <EmptyState title={t('myTickets.empty')} />
                    ) : (
                        <ul className={styles.myTicketsList}>
                            {myTickets.map(ticket => (
                                <li key={ticket.id} className={styles.myTicketItem}>
                                    <div className={styles.myTicketHeader}>
                                        <span className={styles.myTicketTitle}>
                                            {ticket.title || t('myTickets.noTitle')}
                                        </span>
                                        {ticket.status && (
                                            <span className={styles.myTicketStatus}>{ticket.status}</span>
                                        )}
                                    </div>
                                    <div className={styles.myTicketMeta}>
                                        {ticket.reason?.title && (
                                            <span className={styles.myTicketReason}>{ticket.reason.title}</span>
                                        )}
                                        <span className={styles.myTicketPriority}>
                                            {t('myTickets.priorityLabel')}: {t(`priority.${ticket.priority}`)}
                                        </span>
                                        {ticket.createdAt && (
                                            <span className={styles.myTicketDate}>
                                                {new Date(ticket.createdAt).toLocaleDateString()}
                                            </span>
                                        )}
                                    </div>
                                    {ticket.description && (
                                        <p className={styles.myTicketDesc}>{ticket.description}</p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ) : (
                <div className={styles.formWrapper}>

                <form ref={formRef} onSubmit={handleFormSubmit} className={styles.form} noValidate>

                    {/* Subject */}
                    <div className={styles.section}>
                        <h2>{t('form.titleLabel')}</h2>
                        <input
                            type="text"
                            className={styles.textInput}
                            placeholder={t('form.titlePlaceholder')}
                            value={title}
                            onChange={e => setTitle(e.target.value)}
                            disabled={isSubmitting}
                        />
                    </div>

                    {/* Reason */}
                    <div className={styles.section}>
                        <h2>{t('form.reasonLabel')}</h2>
                        <SelectSearch
                            options={reasonOptions}
                            value={reasonIri}
                            onChange={setReasonIri}
                            placeholder={t('form.reasonPlaceholder')}
                            loading={loadingReasons}
                            disabled={isSubmitting}
                        />
                    </div>

                    {/* Priority */}
                    <div className={styles.section}>
                        <h2>{t('form.priorityLabel')}</h2>
                        <SelectSearch
                            options={priorityOptions}
                            value={priority}
                            onChange={setPriority}
                            placeholder={t('form.priorityPlaceholder')}
                            disabled={isSubmitting}
                        />
                    </div>

                    {/* Guest email — only for unauthenticated users */}
                    {!isAuth && (
                        <div className={styles.section}>
                            <h2>{t('form.emailLabel')}</h2>
                            <input
                                type="email"
                                className={styles.textInput}
                                placeholder={t('form.emailPlaceholder')}
                                value={guestEmail}
                                onChange={e => setGuestEmail(e.target.value)}
                                disabled={isSubmitting}
                            />
                        </div>
                    )}

                    {/* Description */}
                    <div className={styles.section}>
                        <h2>{t('form.descriptionLabel')}</h2>
                        <textarea
                            className={styles.textarea}
                            placeholder={t('form.descriptionPlaceholder')}
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                            rows={5}
                            disabled={isSubmitting}
                        />
                    </div>

                    {/* Photos */}
                    <div className={styles.section}>
                        <h2>{t('form.photosLabel')}</h2>
                        <Grid
                            photos={photos}
                            onChange={setPhotos}
                            getImageUrl={url => formatTicketImageUrl(url)}
                            onClickPhoto={index => gallery.openGallery(
                                photos.slice(0, index).filter(p => p.type === 'new').length
                            )}
                            inputId="tech-support-photos"
                            photoAlt={t('form.photoAlt')}
                            disabled={isSubmitting}
                        />
                    </div>

                    {/* Submit */}
                    <div className={styles.submitSection}>
                        <EditActions
                            onSave={submitForm}
                            onCancel={() => navigate(-1)}
                            saveDisabled={isSubmitting}
                            className={styles.editActionsLarge}
                        />
                    </div>
                </form>
                </div>
            )}

            {/* Preview for uploaded photos */}
            {photoUrls.length > 0 && (
                <Preview
                    isOpen={gallery.isOpen}
                    images={photoUrls}
                    currentIndex={gallery.currentIndex}
                    onClose={gallery.closeGallery}
                    onNext={gallery.goToNext}
                    onPrevious={gallery.goToPrevious}
                    onSelectImage={gallery.selectImage}
                />
            )}

            <Status
                type="success"
                isOpen={showSuccess}
                onClose={() => setShowSuccess(false)}
                message={t('success')}
            />
            <Status
                type="error"
                isOpen={showError}
                onClose={() => setShowError(false)}
                message={errorMessage}
            />
            <Status
                type="warning"
                isOpen={showWarning}
                onClose={() => setShowWarning(false)}
                message={warningMessage}
            />
        </div>
    );
}

export default TechSupport;
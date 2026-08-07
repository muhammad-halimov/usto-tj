import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import type * as React from 'react';
import { useTranslation } from 'react-i18next';
import { IoSend, IoAttach, IoPricetagOutline } from 'react-icons/io5';
import styles from './TechSupportThread.module.scss';
import { universalApiRequest } from '../../../utils/apiUtils';
import { getUserData } from '../../../utils/authUtils';
import { getFormattedDate } from '../../../utils/timeUtils';
import { uploadPhotos, formatTechSupportImageUrl, formatTechSupportMessageImageUrl } from '../../../utils/imageUtils';
import { decodeHtmlEntities } from '../../../utils/textUtils';
import { getAppealReasons } from '../../../utils/dataCacheUtils';
import { useLanguageChange } from '../../../hooks';
import Grid, { type PhotoItem } from '../../../shared/ui/Photo/Grid';
import { Preview, usePreview } from '../../../shared/ui/Photo/Preview';
import { EmptyState } from '../../../widgets/EmptyState';
import { STATUS_ICONS, PRIORITY_ICONS, type SupportTicket, type TechSupportMessage, type TechSupportStatus } from '../types';

interface TechSupportThreadProps {
    ticketId: number;
}

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB — same cap as Chat's attach flow

const formatMessageTime = (dateString?: string): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    return `${getFormattedDate(dateString)} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
};

/**
 * Message thread for a single tech-support ticket — a full-width ticket/reply view
 * (not a chat-bubble layout). The composer (attach icon + single-line input + send,
 * with the photo grid appearing only once something's attached) mirrors Chat.tsx's
 * `.chatInput` exactly, reusing the same `Grid` / `usePreview` + `Preview` / `uploadPhotos`
 * building blocks rather than reinventing them.
 * Not real-time (no SSE/polling) by design, unlike the full Chat page.
 */
function TechSupportThread({ ticketId }: TechSupportThreadProps) {
    const { t } = useTranslation('techSupport');
    const currentUserId = getUserData()?.id;

    const [ticket, setTicket] = useState<SupportTicket | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    // `ticket.reason.title` comes embedded in the ticket response and doesn't seem to respect
    // ?locale= the way a direct GET /api/appeal-reasons?locale= does — look the title up by id
    // from that (correctly locale-fetched, cached) list instead, same fix as the tickets table.
    const [reasonTitleById, setReasonTitleById] = useState<Map<number, string>>(new Map());
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [message, setMessage] = useState('');
    const [photos, setPhotos] = useState<PhotoItem[]>([]);
    const [isSending, setIsSending] = useState(false);
    const composePreviewUrls = photos.filter((p): p is Extract<PhotoItem, { type: 'new' }> => p.type === 'new').map(p => p.previewUrl);
    const composeGallery = usePreview({ images: composePreviewUrls });

    const fetchTicket = useCallback(async () => {
        try {
            setError('');
            const data: SupportTicket = await universalApiRequest(`/api/tech-supports/${ticketId}`);
            setTicket(data);
        } catch {
            setError(t('thread.loadError'));
        } finally {
            setIsLoading(false);
        }
    }, [ticketId, t]);

    // Unfiltered — a ticket's `reason` isn't restricted to `applicableTo=support` server-side
    // (e.g. tickets created from a report/complaint flow can carry an "overall"-tagged reason),
    // so scoping this to just the support reasons would leave those ids unresolved.
    const fetchReasons = useCallback(async () => {
        try {
            const list = await getAppealReasons();
            setReasonTitleById(new Map(list.map(r => [r.id, r.title])));
        } catch {
            // Non-critical — falls back to the (possibly unlocalized) embedded ticket.reason.title.
        }
    }, []);

    useEffect(() => {
        fetchTicket();
        fetchReasons();
    }, [fetchTicket, fetchReasons]);

    // ticket.reason.title (like Category/Occupation/etc.) is localized server-side — it
    // doesn't update just because i18next's UI strings do, so re-fetch on language switch.
    useLanguageChange(() => {
        fetchTicket();
        fetchReasons();
    });

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [ticket?.messages?.length]);

    const triggerFileInput = () => fileInputRef.current?.click();

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (files && files.length > 0) {
            const newItems: PhotoItem[] = Array.from(files)
                .filter(file => file.type.startsWith('image/') && file.size <= MAX_FILE_SIZE)
                .map(file => ({ type: 'new' as const, file, previewUrl: URL.createObjectURL(file) }));
            setPhotos(prev => [...prev, ...newItems]);
        }
        e.target.value = '';
    };

    const handleSend = async () => {
        const text = message.trim();
        if ((!text && photos.length === 0) || isSending) return;
        setIsSending(true);
        try {
            const body: Record<string, string> = { techSupport: `/api/tech-supports/${ticketId}` };
            if (text) body.description = text;

            const result: TechSupportMessage = await universalApiRequest('/api/tech-support-messages', {
                method: 'POST',
                body,
            });

            const newFiles = photos.filter((p): p is Extract<PhotoItem, { type: 'new' }> => p.type === 'new').map(p => p.file);
            if (newFiles.length > 0 && result?.id) {
                try {
                    await uploadPhotos('tech-support-messages', result.id, newFiles);
                } catch {
                    // Photo upload failures are non-critical
                }
            }

            setMessage('');
            setPhotos([]);
            await fetchTicket();
        } catch {
            setError(t('thread.sendError'));
        } finally {
            setIsSending(false);
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSend();
        }
    };

    // Normalized defensively in case the API ever sends different casing than our
    // 'new'|'renewed'|'in_progress'|'resolved'|'closed' assumption — otherwise the status
    // badge would silently get neither its color/icon nor its translation.
    const statusKey = ticket?.status ? (ticket.status.toLowerCase() as TechSupportStatus) : null;
    const statusLabel = statusKey && ticket?.status ? t(`myTickets.statuses.${statusKey}`, ticket.status) : '';
    const StatusIcon = statusKey ? STATUS_ICONS[statusKey] : null;
    const priorityKey = ticket?.priority != null ? String(ticket.priority) : null;
    const PriorityIcon = priorityKey ? PRIORITY_ICONS[priorityKey] : null;
    const administrantName = ticket?.administrant
        ? `${ticket.administrant.surname ?? ''} ${ticket.administrant.name ?? ''}`.trim()
        : '';

    // Current user's own name, for "имя (роль)" labels on your own replies — getUserData()
    // returns the full cached profile, not just the id.
    const myName = useMemo(() => {
        const me = getUserData();
        return me ? `${me.surname ?? ''} ${me.name ?? ''}`.trim() : '';
    }, []);

    // Flattened list of every already-uploaded image in the thread (original request +
    // each message — two different upload folders, see imageUtils.ts), so clicking any
    // sent attachment opens it at the right position.
    const sentImageUrls = useMemo(() => {
        if (!ticket) return [];
        const urls: string[] = (ticket.images ?? []).map(img => formatTechSupportImageUrl(img.image));
        (ticket.messages ?? []).forEach(m => (m.images ?? []).forEach(img => urls.push(formatTechSupportMessageImageUrl(img.image))));
        return urls;
    }, [ticket]);
    const sentGallery = usePreview({ images: sentImageUrls });
    const openSentImage = (url: string) => {
        const index = sentImageUrls.indexOf(url);
        sentGallery.openGallery(index >= 0 ? index : 0);
    };

    return (
        <div className={styles.thread}>
            <div className={styles.threadHeader}>
                {ticket && (
                    <div className={styles.threadHeaderInfo}>
                        <h2 className={styles.threadTitle}>{ticket.title || t('myTickets.noTitle')}</h2>
                        <div className={styles.threadMeta}>
                            {statusKey && (
                                <span className={`${styles.badge} ${styles.statusBadge} ${styles[`status_${statusKey}`] ?? ''}`}>
                                    {StatusIcon && <StatusIcon />}
                                    {statusLabel}
                                </span>
                            )}
                            {priorityKey && (
                                <span className={`${styles.badge} ${styles.statusBadge} ${styles[`priority_${priorityKey}`] ?? ''}`}>
                                    {PriorityIcon && <PriorityIcon />}
                                    {t(`priority.${priorityKey}`)}
                                </span>
                            )}
                            {(() => {
                                const reasonTitle = (ticket.reason?.id != null ? reasonTitleById.get(ticket.reason.id) : undefined) ?? ticket.reason?.title;
                                return reasonTitle && (
                                    <span className={`${styles.badge} ${styles.statusBadge}`}>
                                        <IoPricetagOutline />
                                        {reasonTitle}
                                    </span>
                                );
                            })()}
                            {administrantName && (
                                <span className={styles.reasonTag}>{t('thread.support')}: {administrantName}</span>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {isLoading ? (
                <EmptyState isLoading />
            ) : !ticket ? (
                <EmptyState title={error || t('thread.loadError')} onRefresh={fetchTicket} />
            ) : (
                <>
                    <div className={styles.messages}>
                        <div className={styles.message}>
                            <div className={styles.messageHeader}>
                                <span className={styles.messageAuthorName}>{t('thread.originalRequest')}</span>
                                <span className={styles.messageTime}>{formatMessageTime(ticket.createdAt)}</span>
                            </div>
                            <div className={styles.messageBody}>{decodeHtmlEntities(ticket.description)}</div>
                            {(ticket.images ?? []).length > 0 && (
                                <div className={styles.messageImages}>
                                    {ticket.images!.map(img => {
                                        const url = formatTechSupportImageUrl(img.image);
                                        return (
                                            <img
                                                key={img.id}
                                                src={url}
                                                alt=""
                                                className={styles.messageImage}
                                                onClick={() => openSentImage(url)}
                                            />
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {(ticket.messages ?? []).map(msg => {
                            const isMine = !!currentUserId && msg.author?.id === currentUserId;
                            const authorName = msg.author
                                ? `${msg.author.surname ?? ''} ${msg.author.name ?? ''}`.trim()
                                : '';
                            // Name shown next to the role, not instead of it — falls back to
                            // just the role label when the name isn't known.
                            const authorLabel = isMine
                                ? (myName ? `${myName} (${t('thread.you')})` : t('thread.you'))
                                : (authorName ? `${authorName} (${t('thread.support')})` : t('thread.support'));

                            return (
                                <div key={msg.id} className={`${styles.message} ${isMine ? styles.messageMine : styles.messageSupport}`}>
                                    <div className={styles.messageHeader}>
                                        <span className={styles.messageAuthorName}>{authorLabel}</span>
                                        <span className={styles.messageTime}>{formatMessageTime(msg.createdAt)}</span>
                                    </div>
                                    {msg.description && <div className={styles.messageBody}>{decodeHtmlEntities(msg.description)}</div>}
                                    {(msg.images ?? []).length > 0 && (
                                        <div className={styles.messageImages}>
                                            {msg.images.map(img => {
                                                const url = formatTechSupportMessageImageUrl(img.image);
                                                return (
                                                    <img
                                                        key={img.id}
                                                        src={url}
                                                        alt=""
                                                        className={styles.messageImage}
                                                        onClick={() => openSentImage(url)}
                                                    />
                                                );
                                            })}
                                        </div>
                                    )}
                                </div>
                            );
                        })}

                        {(ticket.messages ?? []).length === 0 && (
                            <div className={styles.noMessages}>{t('thread.noMessages')}</div>
                        )}
                        <div ref={messagesEndRef} />
                    </div>

                    {error && <div className={styles.threadError}>{error}</div>}

                    <input
                        type="file"
                        ref={fileInputRef}
                        style={{ display: 'none' }}
                        onChange={handleFileSelect}
                        multiple
                        accept="image/*"
                    />

                    <div className={styles.chatInput}>
                        <button
                            type="button"
                            className={styles.attachButton}
                            onClick={triggerFileInput}
                            disabled={isSending}
                            aria-label={t('form.photosLabel')}
                        >
                            <IoAttach />
                        </button>

                        <input
                            type="text"
                            placeholder={t('thread.placeholder')}
                            className={styles.inputField}
                            value={message}
                            onChange={e => setMessage(e.target.value)}
                            onKeyPress={handleKeyPress}
                            disabled={isSending}
                        />

                        <button
                            type="button"
                            className={styles.sendBtn}
                            onClick={handleSend}
                            disabled={isSending || (!message.trim() && photos.length === 0)}
                            aria-label={t('thread.send')}
                        >
                            <IoSend />
                        </button>
                    </div>

                    {photos.length > 0 && (
                        <Grid
                            photos={photos}
                            onChange={setPhotos}
                            getImageUrl={formatTechSupportMessageImageUrl}
                            onClickPhoto={index => composeGallery.openGallery(index)}
                            inputId="ts-thread-compose-photos"
                            photoAlt={t('form.photoAlt')}
                            disabled={isSending}
                        />
                    )}
                </>
            )}

            <Preview
                isOpen={composeGallery.isOpen}
                images={composePreviewUrls}
                currentIndex={composeGallery.currentIndex}
                onClose={composeGallery.closeGallery}
                onNext={composeGallery.goToNext}
                onPrevious={composeGallery.goToPrevious}
                onSelectImage={composeGallery.selectImage}
            />
            <Preview
                isOpen={sentGallery.isOpen}
                images={sentImageUrls}
                currentIndex={sentGallery.currentIndex}
                onClose={sentGallery.closeGallery}
                onNext={sentGallery.goToNext}
                onPrevious={sentGallery.goToPrevious}
                onSelectImage={sentGallery.selectImage}
            />
        </div>
    );
}

export default TechSupportThread;

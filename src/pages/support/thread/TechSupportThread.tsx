import { useState, useEffect, useCallback, useRef } from 'react';
import type * as React from 'react';
import { useTranslation } from 'react-i18next';
import { IoSend } from 'react-icons/io5';
import styles from './TechSupportThread.module.scss';
import { universalApiRequest } from '../../../utils/apiUtils';
import { getUserData } from '../../../utils/authUtils';
import { getFormattedDate } from '../../../utils/timeUtils';
import { EmptyState } from '../../../widgets/EmptyState';
import type { SupportTicket } from '../types';

interface TechSupportThreadProps {
    ticketId: number;
}

const formatMessageTime = (dateString?: string): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    return `${getFormattedDate(dateString)} ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
};

/**
 * Primitive message thread for a single tech-support ticket.
 * Replaces the tickets table when a ticket is opened (see TechSupport.tsx) —
 * fetches the ticket + its messages, lets the author reply, and refetches on send.
 * Not real-time (no SSE/polling) by design, unlike the full Chat page.
 */
function TechSupportThread({ ticketId }: TechSupportThreadProps) {
    const { t } = useTranslation('techSupport');
    const currentUserId = getUserData()?.id;

    const [ticket, setTicket] = useState<SupportTicket | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [isSending, setIsSending] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

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

    useEffect(() => { fetchTicket(); }, [fetchTicket]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [ticket?.messages?.length]);

    const handleSend = async () => {
        const text = message.trim();
        if (!text || isSending) return;
        setIsSending(true);
        try {
            await universalApiRequest('/api/tech-support-messages', {
                method: 'POST',
                body: { techSupport: `/api/tech-supports/${ticketId}`, description: text },
            });
            setMessage('');
            await fetchTicket();
        } catch {
            setError(t('thread.sendError'));
        } finally {
            setIsSending(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    const statusLabel = ticket?.status ? t(`myTickets.statuses.${ticket.status}`, ticket.status) : '';

    return (
        <div className={styles.thread}>
            <div className={styles.threadHeader}>
                {ticket && (
                    <div className={styles.threadHeaderInfo}>
                        <h2 className={styles.threadTitle}>{ticket.title || t('myTickets.noTitle')}</h2>
                        <div className={styles.threadMeta}>
                            {ticket.status && (
                                <span className={`${styles.badge} ${styles[`status_${ticket.status}`] ?? ''}`}>
                                    {statusLabel}
                                </span>
                            )}
                            {ticket.priority && (
                                <span className={styles.badge}>{t(`priority.${ticket.priority}`)}</span>
                            )}
                            {ticket.reason?.title && <span className={styles.reasonTag}>{ticket.reason.title}</span>}
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
                        <div className={`${styles.message} ${styles.messageIncoming}`}>
                            <div className={styles.messageAuthor}>{t('thread.originalRequest')}</div>
                            <div className={styles.messageBubble}>{ticket.description}</div>
                            <div className={styles.messageTime}>{formatMessageTime(ticket.createdAt)}</div>
                        </div>

                        {(ticket.messages ?? []).map(msg => {
                            const isMine = !!currentUserId && msg.author?.id === currentUserId;
                            const authorName = msg.author
                                ? `${msg.author.surname ?? ''} ${msg.author.name ?? ''}`.trim()
                                : '';
                            return (
                                <div key={msg.id} className={`${styles.message} ${isMine ? styles.messageOutgoing : styles.messageIncoming}`}>
                                    <div className={styles.messageAuthor}>
                                        {isMine ? t('thread.you') : (authorName || t('thread.support'))}
                                    </div>
                                    <div className={styles.messageBubble}>{msg.description}</div>
                                    <div className={styles.messageTime}>{formatMessageTime(msg.createdAt)}</div>
                                </div>
                            );
                        })}

                        {(ticket.messages ?? []).length === 0 && (
                            <div className={styles.noMessages}>{t('thread.noMessages')}</div>
                        )}
                        <div ref={messagesEndRef} />
                    </div>

                    {error && <div className={styles.threadError}>{error}</div>}

                    <div className={styles.replyBox}>
                        <textarea
                            className={styles.replyInput}
                            value={message}
                            onChange={e => setMessage(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder={t('thread.placeholder')}
                            rows={2}
                            disabled={isSending}
                        />
                        <button
                            type="button"
                            className={styles.sendBtn}
                            onClick={handleSend}
                            disabled={isSending || !message.trim()}
                            aria-label={t('thread.send')}
                        >
                            <IoSend />
                        </button>
                    </div>
                </>
            )}
        </div>
    );
}

export default TechSupportThread;

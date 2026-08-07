/**
 * Shared types for the Tech Support feature (form, tickets table, message thread).
 * Kept local to the page (not in `entities/`) since the backend doesn't expose
 * a dedicated Hydra type for TechSupport beyond what's used here — see API_REFERENCE.md §11.
 */

export interface AppealReason {
    id: number;
    title: string;
}

export interface TechSupportAuthor {
    id: number;
    name: string | null;
    surname: string | null;
}

export interface TechSupportImage {
    id: number;
    image: string;
}

export interface TechSupportMessage {
    id: number;
    author: TechSupportAuthor | null;
    description: string | null;
    images: TechSupportImage[];
    createdAt: string;
    updatedAt?: string | null;
}

/** `TechSupport.STATUSES` per API_REFERENCE.md §11. */
export type TechSupportStatus = 'new' | 'renewed' | 'in_progress' | 'resolved' | 'closed';

export interface SupportTicket {
    id: number;
    title: string;
    description: string;
    priority: string;
    status?: TechSupportStatus;
    createdAt?: string;
    updatedAt?: string | null;
    reason?: { id?: number; title?: string };
    administrant?: TechSupportAuthor | null;
    author?: TechSupportAuthor | null;
    images?: TechSupportImage[];
    messages?: TechSupportMessage[];
    /** Present only in the POST /tech-supports response for guest (unauthenticated) tickets. */
    guestAccessToken?: string;
}

export const TECH_SUPPORT_STATUSES: TechSupportStatus[] = ['new', 'renewed', 'in_progress', 'resolved', 'closed'];

/**
 * A ticket's own `updatedAt` only moves when the TechSupport entity itself changes
 * (status/priority/title/…) — adding a reply creates a separate `TechSupportMessage`
 * row and doesn't touch it (see API_REFERENCE.md §11: messages only emit their own
 * Mercure event, nothing bumps the parent). So "last activity" for display/sort
 * purposes is whichever is more recent: the ticket's own `updatedAt`, or its latest
 * message's `createdAt`.
 */
export function getLastActivityAt(ticket: SupportTicket): string | undefined {
    const timestamps = [
        ticket.updatedAt,
        ...(ticket.messages ?? []).map(m => m.createdAt),
    ].filter((d): d is string => !!d);
    if (timestamps.length === 0) return undefined;
    return timestamps.reduce((latest, cur) => (new Date(cur) > new Date(latest) ? cur : latest));
}

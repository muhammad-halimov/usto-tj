import type { User } from '../../Api';
import type { Occupation } from '../../Api';
import type { SocialNetwork } from '../../Api';
import type { Phone } from '../../Api';
import type { Ticket } from '../../Api';
import type { WorkExample } from '../../Api';
import type { AddressFormData } from '../Address';
import type { EducationItem } from '../Education';

/**
 * Flattened profile data used by the Profile page.
 * Combines raw User fields with computed values (fullName, avatar URL, etc.)
 * that are resolved during profile fetch in Profile.tsx.
 */
export type ProfileData =
    Pick<User, 'id' | 'email' | 'gender' | 'dateOfBirth' | 'rating' | 'isOnline' | 'lastSeen'> & {
        fullName: string;
        specialties: Occupation[];
        reviews: number;
        avatar: string | null;
        education: EducationItem[];
        workExamples: WorkExample[];
        workArea: string;
        addresses: AddressFormData[];
        canWorkRemotely: boolean;
        services: Ticket[];
        socialNetworks: SocialNetwork[];
        phones: Phone[];
    };

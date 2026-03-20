export type User = {
    id: number;
    prenom: string;
    nom: string;
    name: string; // Accessor: prenom + ' ' + nom
    email: string;
    role: 'admin' | 'enseignant' | 'etudiant';
    locale: 'fr' | 'en';
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};

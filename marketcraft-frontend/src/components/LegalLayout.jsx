import React from 'react';
import { Link } from 'react-router-dom';
import { ChevronLeft } from 'lucide-react';

/**
 * Gabarit commun aux pages légales (confidentialité, mentions légales, cookies).
 * Fournit le fil d'Ariane, le titre, la date de mise à jour et la mise en forme du contenu.
 */
export default function LegalLayout({ title, updated, children }) {
  return (
    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      <Link
        to="/"
        className="inline-flex items-center gap-1 text-sm text-primary hover:underline mb-6"
      >
        <ChevronLeft size={16} /> Retour à l'accueil
      </Link>

      <h1 className="text-3xl sm:text-4xl font-serif font-bold text-gray-800 mb-2 text-balance">
        {title}
      </h1>
      {updated && (
        <p className="text-sm text-gray-500 mb-8">Dernière mise à jour : {updated}</p>
      )}

      <div className="space-y-4 text-gray-700 leading-relaxed text-[15px]">{children}</div>
    </div>
  );
}

/** Titre de section réutilisable au sein d'une page légale. */
export function LegalSection({ title, children }) {
  return (
    <section className="pt-4">
      <h2 className="text-xl font-serif font-semibold text-gray-800 mb-2">{title}</h2>
      <div className="space-y-3">{children}</div>
    </section>
  );
}

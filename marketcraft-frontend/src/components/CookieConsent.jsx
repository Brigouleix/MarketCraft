import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Cookie } from 'lucide-react';

const STORAGE_KEY = 'cookie_consent';

/**
 * Bandeau de consentement aux cookies (RGPD).
 * S'affiche tant qu'aucun choix n'a été enregistré. Le refus est aussi
 * accessible que l'acceptation. Le choix est mémorisé dans le localStorage.
 */
export default function CookieConsent() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    try {
      if (!localStorage.getItem(STORAGE_KEY)) setVisible(true);
    } catch {
      setVisible(true);
    }
  }, []);

  const decide = (choice) => {
    try {
      localStorage.setItem(STORAGE_KEY, choice);
    } catch {
      /* stockage indisponible : on masque simplement le bandeau */
    }
    setVisible(false);
  };

  if (!visible) return null;

  return (
    <div
      role="dialog"
      aria-live="polite"
      aria-label="Consentement aux cookies"
      className="fixed inset-x-0 bottom-0 z-50 p-4 sm:p-6"
    >
      <div className="max-w-4xl mx-auto bg-white border border-secondary-300 rounded-2xl shadow-craft p-5 sm:p-6 flex flex-col md:flex-row md:items-center gap-4">
        <div className="flex items-start gap-3 flex-1">
          <div className="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center flex-shrink-0">
            <Cookie size={20} className="text-primary" />
          </div>
          <p className="text-sm text-gray-600 leading-relaxed">
            Nous utilisons des cookies strictement nécessaires au fonctionnement du site (connexion,
            panier). Aucun cookie de traçage ou publicitaire n'est utilisé. En savoir plus dans notre{' '}
            <Link to="/cookies" className="text-primary hover:underline font-medium">
              politique de cookies
            </Link>
            .
          </p>
        </div>

        <div className="flex gap-2 flex-shrink-0">
          <button
            type="button"
            onClick={() => decide('refused')}
            className="text-sm text-gray-600 border border-gray-300 hover:bg-gray-50 px-4 py-2.5 rounded-lg transition-colors whitespace-nowrap"
          >
            Continuer sans accepter
          </button>
          <button
            type="button"
            onClick={() => decide('accepted')}
            className="btn-primary text-sm px-5 py-2.5 whitespace-nowrap"
          >
            Tout accepter
          </button>
        </div>
      </div>
    </div>
  );
}

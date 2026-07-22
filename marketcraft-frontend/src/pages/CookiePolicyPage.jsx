import React from 'react';
import { Link } from 'react-router-dom';
import LegalLayout, { LegalSection } from '../components/LegalLayout';

export default function CookiePolicyPage() {
  return (
    <LegalLayout title="Politique de cookies" updated="[à compléter — ex. 22/07/2026]">
      <p>
        Cette page explique quels cookies et technologies de stockage similaires sont utilisés sur
        MarketCraft, à quoi ils servent, et comment vous pouvez les gérer.
      </p>

      <LegalSection title="1. Qu'est-ce qu'un cookie ?">
        <p>
          Un cookie est un petit fichier déposé sur votre appareil lors de la visite d'un site.
          MarketCraft utilise principalement le <strong>stockage local</strong> du navigateur
          (localStorage), une technologie similaire, pour faire fonctionner l'application.
        </p>
      </LegalSection>

      <LegalSection title="2. Cookies strictement nécessaires">
        <p>
          Ces éléments sont indispensables au fonctionnement du site et ne peuvent pas être
          désactivés&nbsp;:
        </p>
        <ul className="list-disc pl-6 space-y-1">
          <li>
            <strong>Jeton d'authentification (JWT)&nbsp;:</strong> vous maintient connecté à votre
            compte entre les pages. Stocké dans le localStorage.
          </li>
          <li>
            <strong>Panier&nbsp;:</strong> conserve le contenu de votre panier, propre à votre
            compte, entre deux visites.
          </li>
          <li>
            <strong>Choix de consentement&nbsp;:</strong> mémorise votre réponse au bandeau cookies
            pour ne plus vous la redemander.
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="3. Cookies de mesure d'audience et publicitaires">
        <p>
          À ce jour, MarketCraft <strong>n'utilise aucun cookie tiers</strong> de mesure d'audience,
          de traçage ou de publicité. Si de tels outils étaient ajoutés à l'avenir, ils ne seraient
          déposés qu'après votre consentement explicite via le bandeau cookies.
        </p>
      </LegalSection>

      <LegalSection title="4. Gérer votre consentement">
        <p>
          Lors de votre première visite, un bandeau vous permet d'accepter ou de refuser les cookies
          non essentiels. Les éléments strictement nécessaires restent actifs car ils conditionnent
          le fonctionnement du site.
        </p>
        <p>
          Vous pouvez à tout moment supprimer ces données en vidant le stockage local et les cookies
          de votre navigateur (généralement via les réglages « Confidentialité » ou l'historique de
          navigation).
        </p>
      </LegalSection>

      <LegalSection title="5. En savoir plus">
        <p>
          Le traitement de vos données personnelles est décrit dans notre{' '}
          <Link to="/confidentialite" className="text-primary hover:underline">
            politique de confidentialité
          </Link>
          .
        </p>
      </LegalSection>
    </LegalLayout>
  );
}

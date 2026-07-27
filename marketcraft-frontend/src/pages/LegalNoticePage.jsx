import React from 'react';
import LegalLayout, { LegalSection } from '../components/LegalLayout';

export default function LegalNoticePage() {
  return (
    <LegalLayout title="Mentions légales" updated="27/07/2026">
      <p>
        Conformément à la loi n° 2004-575 du 21 juin 2004 pour la confiance dans l'économie
        numérique, les informations suivantes sont portées à la connaissance des utilisateurs du
        site MarketCraft.
      </p>

      <LegalSection title="Éditeur du site">
        <p>
          <strong>MarketCraft</strong>
          <br />
          SAS au capital de 1&nbsp;000&nbsp;€ (projet de démonstration)
          <br />
          Adresse&nbsp;: 12 rue des Artisans, 75011 Paris, France
          <br />
          SIRET&nbsp;: 000&nbsp;000&nbsp;000&nbsp;00000 (numéro fictif) — TVA intracommunautaire&nbsp;: non applicable
          <br />
          E-mail&nbsp;: contact@marketcraft.fr
        </p>
        <p className="text-sm text-gray-500">
          Informations de démonstration&nbsp;: à remplacer par les mentions réelles de l'éditeur
          avant toute mise en production.
        </p>
      </LegalSection>

      <LegalSection title="Directeur de la publication">
        <p>L'équipe MarketCraft.</p>
      </LegalSection>

      <LegalSection title="Hébergement">
        <p>
          Le site est hébergé sur&nbsp;:
          <br />
          <strong>Environnement de démonstration</strong> (serveur de développement local). En
          production, l'hébergeur sera précisé ici.
        </p>
      </LegalSection>

      <LegalSection title="Propriété intellectuelle">
        <p>
          L'ensemble des contenus du site (structure, textes, logo, éléments graphiques) est protégé
          par le droit de la propriété intellectuelle. Les visuels et descriptions des produits
          restent la propriété de leurs artisans respectifs. Toute reproduction non autorisée est
          interdite.
        </p>
      </LegalSection>

      <LegalSection title="Responsabilité">
        <p>
          MarketCraft met en relation des artisans-vendeurs et des acheteurs. L'éditeur s'efforce
          d'assurer l'exactitude des informations diffusées mais ne saurait être tenu responsable
          des contenus publiés par les vendeurs ni des éventuelles indisponibilités du service.
        </p>
      </LegalSection>
    </LegalLayout>
  );
}

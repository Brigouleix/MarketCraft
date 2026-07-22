import React from 'react';
import LegalLayout, { LegalSection } from '../components/LegalLayout';

export default function LegalNoticePage() {
  return (
    <LegalLayout title="Mentions légales" updated="[à compléter — ex. 22/07/2026]">
      <p>
        Conformément à la loi n° 2004-575 du 21 juin 2004 pour la confiance dans l'économie
        numérique, les informations suivantes sont portées à la connaissance des utilisateurs du
        site MarketCraft.
      </p>

      <LegalSection title="Éditeur du site">
        <p>
          <strong>[Raison sociale / nom de l'éditeur à compléter]</strong>
          <br />
          [Statut juridique et capital le cas échéant]
          <br />
          Adresse&nbsp;: [adresse postale à compléter]
          <br />
          SIRET&nbsp;: [numéro à compléter] — TVA intracommunautaire&nbsp;: [le cas échéant]
          <br />
          E-mail&nbsp;: [email de contact à compléter]
        </p>
      </LegalSection>

      <LegalSection title="Directeur de la publication">
        <p>[Nom du directeur de la publication à compléter].</p>
      </LegalSection>

      <LegalSection title="Hébergement">
        <p>
          Le site est hébergé par&nbsp;:
          <br />
          <strong>[Nom de l'hébergeur à compléter]</strong>, [adresse de l'hébergeur], [contact].
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

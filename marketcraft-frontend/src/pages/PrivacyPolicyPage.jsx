import React from 'react';
import { Link } from 'react-router-dom';
import LegalLayout, { LegalSection } from '../components/LegalLayout';

export default function PrivacyPolicyPage() {
  return (
    <LegalLayout title="Politique de confidentialité" updated="[à compléter — ex. 22/07/2026]">
      <p>
        La présente politique décrit la manière dont MarketCraft collecte, utilise et protège
        les données personnelles de ses utilisateurs, conformément au Règlement Général sur la
        Protection des Données (RGPD) et à la loi « Informatique et Libertés ».
      </p>

      <LegalSection title="1. Responsable du traitement">
        <p>
          Le responsable du traitement des données est&nbsp;:
          <br />
          <strong>[Nom de l'éditeur / raison sociale à compléter]</strong>, [statut juridique],
          [adresse], joignable à l'adresse <strong>[email de contact à compléter]</strong>.
        </p>
      </LegalSection>

      <LegalSection title="2. Données collectées">
        <p>Dans le cadre de l'utilisation de la plateforme, nous collectons&nbsp;:</p>
        <ul className="list-disc pl-6 space-y-1">
          <li>
            <strong>Données d'identité et de compte&nbsp;:</strong> nom, prénom, adresse e-mail,
            mot de passe (stocké de façon chiffrée et irréversible via l'algorithme bcrypt).
          </li>
          <li>
            <strong>Données de livraison&nbsp;:</strong> adresse postale, ville, code postal, pays.
          </li>
          <li>
            <strong>Données de commande&nbsp;:</strong> produits commandés, montants, statut et
            historique des commandes.
          </li>
          <li>
            <strong>Contenus publiés&nbsp;:</strong> avis, notes et commentaires laissés sur les
            produits.
          </li>
          <li>
            <strong>Données techniques&nbsp;:</strong> jetons d'authentification stockés localement
            dans votre navigateur (voir la <Link to="/cookies" className="text-primary hover:underline">politique de cookies</Link>).
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="3. Finalités et bases légales">
        <ul className="list-disc pl-6 space-y-1">
          <li>Création et gestion de votre compte — <em>exécution du contrat</em>.</li>
          <li>Traitement et suivi de vos commandes et paiements — <em>exécution du contrat</em>.</li>
          <li>Publication d'avis vérifiés — <em>consentement</em>.</li>
          <li>Sécurité de la plateforme et prévention de la fraude — <em>intérêt légitime</em>.</li>
          <li>Respect de nos obligations comptables et légales — <em>obligation légale</em>.</li>
        </ul>
      </LegalSection>

      <LegalSection title="4. Destinataires des données">
        <p>
          Vos données sont destinées aux seuls services habilités de MarketCraft et, le cas échéant,
          à nos sous-traitants techniques (hébergement, prestataire de paiement) agissant sur
          instruction et dans le respect du RGPD. Elles ne sont jamais vendues à des tiers.
        </p>
      </LegalSection>

      <LegalSection title="5. Durée de conservation">
        <p>
          Les données de compte sont conservées tant que le compte est actif. En cas de suppression
          de votre compte, elles sont désactivées puis supprimées ou anonymisées, sous réserve des
          durées légales de conservation applicables aux documents comptables et aux commandes.
        </p>
      </LegalSection>

      <LegalSection title="6. Vos droits">
        <p>Conformément au RGPD, vous disposez des droits suivants&nbsp;:</p>
        <ul className="list-disc pl-6 space-y-1">
          <li>Droit d'accès, de rectification et d'effacement de vos données&nbsp;;</li>
          <li>Droit à la limitation et à l'opposition au traitement&nbsp;;</li>
          <li>Droit à la portabilité de vos données&nbsp;;</li>
          <li>Droit de retirer votre consentement à tout moment.</li>
        </ul>
        <p>
          Vous pouvez exercer une partie de ces droits directement depuis votre{' '}
          <Link to="/profil" className="text-primary hover:underline">espace personnel</Link>&nbsp;:
          modification de vos informations, changement de mot de passe et suppression de votre compte.
          Pour toute autre demande, contactez-nous à <strong>[email de contact à compléter]</strong>.
          Vous pouvez également introduire une réclamation auprès de la CNIL (www.cnil.fr).
        </p>
      </LegalSection>

      <LegalSection title="7. Sécurité">
        <p>
          Nous mettons en œuvre des mesures techniques adaptées&nbsp;: mots de passe chiffrés
          (bcrypt), authentification par jetons signés, requêtes préparées contre les injections,
          contrôle d'accès par rôle et communications sécurisées. Aucune donnée bancaire n'est
          stockée sur nos serveurs.
        </p>
      </LegalSection>

      <LegalSection title="8. Cookies et stockage local">
        <p>
          L'utilisation des cookies et technologies de stockage similaires est détaillée dans notre{' '}
          <Link to="/cookies" className="text-primary hover:underline">politique de cookies</Link>.
        </p>
      </LegalSection>

      <LegalSection title="9. Contact">
        <p>
          Pour toute question relative à cette politique ou à vos données&nbsp;:
          <br />
          <strong>[email de contact / DPO à compléter]</strong>.
        </p>
      </LegalSection>
    </LegalLayout>
  );
}

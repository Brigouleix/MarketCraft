import React from 'react';
import { Link } from 'react-router-dom';
import LegalLayout, { LegalSection } from '../components/LegalLayout';

export default function TermsOfUsePage() {
  return (
    <LegalLayout title="Conditions d'utilisation" updated="27/07/2026">
      <p>
        Les présentes conditions générales d'utilisation (les «&nbsp;Conditions&nbsp;») régissent
        l'accès à la plateforme MarketCraft et son utilisation. MarketCraft est une place de marché
        en ligne dédiée à l'artisanat, mettant en relation des artisans vendeurs et des acheteurs.
        En créant un compte, vous reconnaissez avoir lu et accepté l'intégralité des Conditions.
      </p>

      <LegalSection title="1. Objet">
        <p>
          Les Conditions ont pour objet de définir les modalités de mise à disposition des services
          de la plateforme et les conditions d'utilisation par l'utilisateur. MarketCraft agit en
          qualité d'<strong>intermédiaire technique</strong>&nbsp;: la vente est conclue directement
          entre l'acheteur et l'artisan vendeur.
        </p>
      </LegalSection>

      <LegalSection title="2. Acceptation des conditions">
        <p>
          La création d'un compte suppose l'acceptation expresse et sans réserve des présentes
          Conditions, ainsi que de la{' '}
          <Link to="/confidentialite" className="text-primary hover:underline">
            politique de confidentialité
          </Link>{' '}
          et de la{' '}
          <Link to="/cookies" className="text-primary hover:underline">
            politique de cookies
          </Link>
          . Un utilisateur qui n'accepte pas ces documents ne peut pas s'inscrire ni utiliser la
          plateforme.
        </p>
      </LegalSection>

      <LegalSection title="3. Inscription et compte">
        <ul className="list-disc pl-6 space-y-1">
          <li>
            L'inscription est réservée aux personnes majeures et capables juridiquement.
          </li>
          <li>
            Le rôle (<em>acheteur</em> ou <em>artisan vendeur</em>) est choisi à l'inscription. Un
            compte acheteur ne peut pas mettre de produits en vente&nbsp;; un compte vendeur ne peut
            pas passer commande.
          </li>
          <li>
            Vous vous engagez à fournir des informations exactes et à les tenir à jour depuis votre{' '}
            <Link to="/profil" className="text-primary hover:underline">espace personnel</Link>.
          </li>
          <li>
            Vous êtes seul responsable de la confidentialité de votre mot de passe et de toute
            activité effectuée depuis votre compte.
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="4. Obligations des vendeurs">
        <p>L'artisan vendeur s'engage à&nbsp;:</p>
        <ul className="list-disc pl-6 space-y-1">
          <li>
            ne proposer que des créations dont il détient les droits et qui respectent la
            législation en vigueur&nbsp;;
          </li>
          <li>
            décrire ses produits de manière loyale et exacte (nature, matériaux, dimensions, prix,
            disponibilité) et signaler le caractère fait main lorsqu'il s'applique&nbsp;;
          </li>
          <li>
            honorer les commandes acceptées, assurer l'expédition et tenir le statut de commande à
            jour (en préparation, expédiée, livrée)&nbsp;;
          </li>
          <li>
            respecter le droit de la consommation, notamment les obligations d'information et, le cas
            échéant, le droit de rétractation.
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="5. Obligations des acheteurs">
        <ul className="list-disc pl-6 space-y-1">
          <li>fournir une adresse de livraison exacte au moment de la commande&nbsp;;</li>
          <li>
            régler le prix des produits commandés selon les modalités proposées au moment de la
            commande&nbsp;;
          </li>
          <li>
            n'utiliser la fonction d'avis que pour des achats réellement effectués et livrés (voir
            article&nbsp;6).
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="6. Avis et contenus publiés">
        <p>
          Un avis ne peut être déposé qu'à la suite d'une commande <strong>effectivement livrée</strong>,
          afin de garantir l'authenticité des évaluations. Les avis doivent rester courtois,
          pertinents et exempts de contenu illicite, diffamatoire ou publicitaire. MarketCraft se
          réserve le droit de retirer tout contenu contraire aux présentes Conditions ou à la loi.
        </p>
      </LegalSection>

      <LegalSection title="7. Prix et paiement">
        <p>
          Les prix sont indiqués en euros, toutes taxes comprises le cas échéant. Le vendeur est
          responsable de l'exactitude de ses prix. Aucune donnée bancaire n'est conservée sur les
          serveurs de MarketCraft.
        </p>
      </LegalSection>

      <LegalSection title="8. Propriété intellectuelle">
        <p>
          La marque, le logo, l'interface et les éléments de la plateforme sont protégés. Les
          photographies et descriptions publiées par un vendeur restent sa propriété&nbsp;; en les
          publiant, il concède à MarketCraft une licence non exclusive d'affichage aux seules fins
          du fonctionnement du service.
        </p>
      </LegalSection>

      <LegalSection title="9. Comportements interdits">
        <ul className="list-disc pl-6 space-y-1">
          <li>usurper l'identité d'un tiers ou créer de faux comptes&nbsp;;</li>
          <li>publier des contenus illicites, trompeurs ou contrefaisants&nbsp;;</li>
          <li>
            tenter de contourner la sécurité de la plateforme, l'automatiser ou en extraire les
            données de façon massive&nbsp;;
          </li>
          <li>déposer des avis mensongers ou manipuler les évaluations.</li>
        </ul>
      </LegalSection>

      <LegalSection title="10. Responsabilité">
        <p>
          MarketCraft agit comme intermédiaire et ne saurait être tenu responsable de la qualité,
          de la conformité ou de la livraison des produits, qui relèvent du vendeur. La plateforme
          met en œuvre des moyens raisonnables pour assurer la disponibilité et la sécurité du
          service, sans garantie d'absence totale d'interruption ou d'erreur.
        </p>
      </LegalSection>

      <LegalSection title="11. Suspension et résiliation">
        <p>
          En cas de manquement aux présentes Conditions, MarketCraft peut suspendre ou supprimer le
          compte concerné, après information lorsque cela est possible. Vous pouvez à tout moment
          supprimer votre compte depuis votre{' '}
          <Link to="/profil" className="text-primary hover:underline">espace personnel</Link>.
        </p>
      </LegalSection>

      <LegalSection title="12. Données personnelles">
        <p>
          Le traitement de vos données personnelles est décrit dans notre{' '}
          <Link to="/confidentialite" className="text-primary hover:underline">
            politique de confidentialité
          </Link>
          , conforme au RGPD.
        </p>
      </LegalSection>

      <LegalSection title="13. Modification des conditions">
        <p>
          MarketCraft peut faire évoluer les présentes Conditions. La version applicable est celle
          en vigueur au moment de l'utilisation de la plateforme&nbsp;; la date de dernière mise à
          jour figure en tête de page.
        </p>
      </LegalSection>

      <LegalSection title="14. Droit applicable et litiges">
        <p>
          Les présentes Conditions sont soumises au droit français. En cas de litige, une solution
          amiable sera recherchée avant toute action contentieuse. À défaut, les tribunaux
          compétents seront ceux du ressort applicable selon la réglementation en vigueur. Pour
          toute question&nbsp;: <strong>contact@marketcraft.fr</strong>.
        </p>
      </LegalSection>
    </LegalLayout>
  );
}

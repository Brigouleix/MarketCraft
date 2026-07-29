import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Sparkles, Info } from 'lucide-react';
import ProductCard from './ProductCard';

/**
 * Bloc de recommandations personnalisées.
 *
 * Module « recommandation personnalisée » du cahier des charges (option C).
 * Le composant est agnostique de la source : on lui passe la fonction de
 * récupération, il se charge de l'affichage, du chargement et du silence.
 *
 * Deux principes d'affichage :
 *
 *   - `ia_active` distingue une vraie recommandation d'un repli par
 *     similarité. Le badge le dit explicitement plutôt que de laisser
 *     croire à une suggestion intelligente quand le modèle n'a pas répondu.
 *
 *   - en l'absence de résultat, le composant ne rend RIEN. Un encart vide
 *     intitulé « Nos suggestions » sur une page de commandes donne
 *     l'impression d'une page cassée.
 *
 * @param {function} recuperer  Renvoie une promesse axios ({ data }).
 * @param {string}   titre      Titre de la section.
 * @param {string}   sousTitre  Ligne explicative, optionnelle.
 * @param {string}   cleCache   Clé react-query, doit être unique par usage.
 */
export default function RecommandationsIA({
  recuperer,
  titre = 'Suggestions pour vous',
  sousTitre = null,
  cleCache,
}) {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['recommandations', cleCache],
    queryFn: async () => {
      const { data } = await recuperer();
      return data?.data ?? data;
    },
    staleTime: 1000 * 60 * 5,
    retry: false,
  });

  // Le back-end expose `products` et `produits` ; on accepte les deux.
  const produits = data?.products || data?.produits || [];
  const iaActive = Boolean(data?.ia_active);

  if (isLoading) {
    return (
      <section className="mt-10">
        <div className="h-6 w-56 bg-secondary-300 rounded animate-pulse mb-4" />
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="card h-64 animate-pulse bg-secondary-200" />
          ))}
        </div>
      </section>
    );
  }

  // Panne réseau ou catalogue trop pauvre : on se tait plutôt que d'afficher
  // un message d'erreur pour une fonctionnalité d'agrément.
  if (isError || produits.length === 0) return null;

  return (
    <section className="mt-12">
      <div className="flex flex-wrap items-center gap-3 mb-1">
        <h2 className="font-serif font-bold text-xl text-gray-800">{titre}</h2>

        <span
          className={`inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full ${
            iaActive
              ? 'bg-purple-100 text-purple-700'
              : 'bg-secondary-200 text-gray-600'
          }`}
          title={
            iaActive
              ? 'Sélection établie par un modèle de langage à partir de votre historique.'
              : "Le service d'IA n'a pas répondu : sélection établie par similarité de catégorie et de gamme de prix."
          }
        >
          {iaActive ? <Sparkles size={13} /> : <Info size={13} />}
          {iaActive ? 'Suggestions IA' : 'Sélection par similarité'}
        </span>
      </div>

      {sousTitre && <p className="text-sm text-gray-500 mb-4">{sousTitre}</p>}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        {produits.map((produit) => (
          <div key={produit.id} className="flex flex-col">
            <ProductCard product={produit} compact />

            {/* Le motif n'existe que si le modèle a répondu. */}
            {produit.motif && (
              <p className="text-xs text-gray-500 italic mt-2 px-1 leading-snug">
                {produit.motif}
              </p>
            )}
          </div>
        ))}
      </div>
    </section>
  );
}

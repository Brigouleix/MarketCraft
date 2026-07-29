import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Sparkles, Info, TrendingDown, TrendingUp, Minus, HelpCircle } from 'lucide-react';
import { concurrenceAPI } from '../services/api';

/**
 * Analyse concurrentielle du catalogue d'un vendeur.
 *
 * Ajout hors périmètre du cahier des charges, assumé comme tel : le module
 * IA imposé est la recommandation personnalisée, servie ailleurs.
 *
 * Point de conception à retenir : **tous les chiffres affichés ici sont
 * calculés côté serveur**, jamais produits par le modèle de langage. Ce
 * dernier ne rédige que la synthèse et les conseils. Un prix médian inventé
 * et affiché à un vendeur serait pire qu'une absence d'analyse.
 *
 * Le positionnement se mesure par rapport à la MÉDIANE du marché, pas à la
 * moyenne : une pièce d'exception isolée ferait sinon passer tout le reste
 * du catalogue pour bon marché.
 */

const POSITIONS = {
  moins_cher: {
    label: 'Sous le marché',
    couleur: 'text-emerald-700 bg-emerald-100',
    icone: TrendingDown,
  },
  dans_la_moyenne: {
    label: 'Aligné',
    couleur: 'text-blue-700 bg-blue-100',
    icone: Minus,
  },
  plus_cher: {
    label: 'Au-dessus',
    couleur: 'text-amber-700 bg-amber-100',
    icone: TrendingUp,
  },
  sans_comparable: {
    label: 'Sans comparable',
    couleur: 'text-gray-600 bg-secondary-200',
    icone: HelpCircle,
  },
};

function BadgePosition({ position }) {
  const config = POSITIONS[position] || POSITIONS.sans_comparable;
  const Icone = config.icone;

  return (
    <span
      className={`inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full ${config.couleur}`}
    >
      <Icone size={13} />
      {config.label}
    </span>
  );
}

export default function AnalyseConcurrence() {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['analyse-concurrence'],
    queryFn: async () => {
      const { data } = await concurrenceAPI.get();
      return data?.data ?? data;
    },
    staleTime: 1000 * 60 * 10,
    retry: false,
  });

  if (isLoading) {
    return (
      <div className="space-y-4">
        <div className="h-20 bg-secondary-200 rounded-xl animate-pulse" />
        <div className="card h-64 animate-pulse bg-secondary-200" />
      </div>
    );
  }

  if (isError) {
    // Ici on parle, contrairement aux recommandations : le vendeur a
    // explicitement demandé cette analyse en ouvrant l'onglet.
    const message =
      error?.response?.data?.error ||
      "L'analyse n'a pas pu être calculée. Réessayez dans un instant.";

    return (
      <div className="card p-6 text-center">
        <p className="text-gray-600">{message}</p>
      </div>
    );
  }

  const produits = data?.produits || [];
  const resume = data?.resume || {};
  const iaActive = Boolean(data?.ia_active);

  if (produits.length === 0) {
    return (
      <div className="card p-10 text-center flex flex-col items-center gap-3">
        <HelpCircle size={48} className="text-secondary-400" />
        <h3 className="text-lg font-serif font-semibold text-gray-700">
          Rien à analyser pour l'instant
        </h3>
        <p className="text-gray-500 text-sm max-w-md">
          Ajoutez des produits à votre boutique pour découvrir comment ils se
          situent face aux autres artisans de la plateforme.
        </p>
      </div>
    );
  }

  return (
    <div>
      {/* ── Synthèse ────────────────────────────────────────────────── */}
      <div className="card p-5 mb-6">
        <div className="flex flex-wrap items-center gap-3 mb-3">
          <h3 className="font-serif font-bold text-lg text-gray-800">
            Positionnement de votre catalogue
          </h3>

          <span
            className={`inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full ${
              iaActive ? 'bg-purple-100 text-purple-700' : 'bg-secondary-200 text-gray-600'
            }`}
            title={
              iaActive
                ? 'Commentaire rédigé par un modèle de langage à partir de statistiques calculées par le serveur.'
                : "Le service d'IA n'a pas répondu : seules les statistiques calculées sont affichées."
            }
          >
            {iaActive ? <Sparkles size={13} /> : <Info size={13} />}
            {iaActive ? 'Analyse IA' : 'Statistiques seules'}
          </span>
        </div>

        {data?.synthese && (
          <p className="text-gray-700 leading-relaxed text-sm">{data.synthese}</p>
        )}

        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5">
          {[
            ['Sous le marché', resume.moins_cher, 'text-emerald-700'],
            ['Alignés', resume.dans_la_moyenne, 'text-blue-700'],
            ['Au-dessus', resume.plus_cher, 'text-amber-700'],
            ['Sans comparable', resume.sans_comparable, 'text-gray-500'],
          ].map(([libelle, valeur, couleur]) => (
            <div key={libelle} className="bg-secondary-50 rounded-lg px-4 py-3">
              <p className={`text-2xl font-bold ${couleur}`}>{valeur ?? 0}</p>
              <p className="text-xs text-gray-500 mt-0.5">{libelle}</p>
            </div>
          ))}
        </div>
      </div>

      {/* ── Détail par produit ──────────────────────────────────────── */}
      <div className="card overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-secondary-100">
            <tr className="text-left">
              <th className="px-5 py-3.5 font-semibold text-gray-600">Produit</th>
              <th className="px-5 py-3.5 font-semibold text-gray-600">Votre prix</th>
              <th className="px-5 py-3.5 font-semibold text-gray-600">Marché</th>
              <th className="px-5 py-3.5 font-semibold text-gray-600">Écart</th>
              <th className="px-5 py-3.5 font-semibold text-gray-600">Position</th>
            </tr>
          </thead>

          <tbody className="divide-y divide-secondary-100">
            {produits.map((p) => (
              <React.Fragment key={p.id}>
                <tr className="hover:bg-secondary-50 transition-colors">
                  <td className="px-5 py-4">
                    <p className="font-medium text-gray-800">{p.nom}</p>
                    {p.categorie && (
                      <p className="text-xs text-gray-500 mt-0.5">{p.categorie}</p>
                    )}
                  </td>

                  <td className="px-5 py-4 font-semibold text-primary whitespace-nowrap">
                    {Number(p.prix).toFixed(2)} €
                  </td>

                  <td className="px-5 py-4 text-gray-600 whitespace-nowrap">
                    {p.marche ? (
                      <>
                        <span className="font-medium">
                          {Number(p.marche.mediane).toFixed(2)} €
                        </span>
                        <span className="text-xs text-gray-400 block">
                          {Number(p.marche.min).toFixed(0)} – {Number(p.marche.max).toFixed(0)} €
                          {' · '}
                          {p.concurrents} concurrent{p.concurrents > 1 ? 's' : ''}
                        </span>
                      </>
                    ) : (
                      <span className="text-gray-400">—</span>
                    )}
                  </td>

                  <td className="px-5 py-4 whitespace-nowrap">
                    {p.ecart_median_pct === null ? (
                      <span className="text-gray-400">—</span>
                    ) : (
                      <span
                        className={
                          p.ecart_median_pct > 0 ? 'text-amber-700' : 'text-emerald-700'
                        }
                      >
                        {p.ecart_median_pct > 0 ? '+' : ''}
                        {p.ecart_median_pct} %
                      </span>
                    )}
                  </td>

                  <td className="px-5 py-4">
                    <BadgePosition position={p.positionnement} />
                  </td>
                </tr>

                {/* Le conseil n'existe que si le modèle a répondu. */}
                {p.conseil && (
                  <tr className="bg-purple-50/40">
                    <td colSpan={5} className="px-5 pb-4 pt-0">
                      <p className="text-xs text-purple-900/80 italic flex items-start gap-2">
                        <Sparkles size={13} className="mt-0.5 shrink-0" />
                        {p.conseil}
                      </p>
                    </td>
                  </tr>
                )}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-gray-400 mt-4 leading-relaxed">
        Le positionnement compare chaque produit à la médiane des prix pratiqués
        par les autres boutiques dans les mêmes catégories. Un écart inférieur à
        15 % est considéré comme aligné sur le marché.
      </p>
    </div>
  );
}

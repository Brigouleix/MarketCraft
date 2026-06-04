import React from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  ShoppingBag, TrendingDown, Star, Package,
  CheckCircle, Clock, XCircle, TrendingUp, AlertCircle,
} from 'lucide-react';
import { dashboardAPI } from '../services/api';
import { useAuth } from '../hooks/useAuth';
import { Link } from 'react-router-dom';

const STATUS_CONFIG = {
  en_attente:     { label: 'En attente',    color: 'text-amber-600 bg-amber-100',   icon: Clock        },
  confirmee:      { label: 'Confirmée',     color: 'text-blue-600 bg-blue-100',     icon: CheckCircle  },
  en_preparation: { label: 'En préparation',color: 'text-indigo-600 bg-indigo-100', icon: Package      },
  expediee:       { label: 'Expédiée',      color: 'text-purple-600 bg-purple-100', icon: TrendingUp   },
  livree:         { label: 'Livrée',        color: 'text-green-600 bg-green-100',   icon: CheckCircle  },
  annulee:        { label: 'Annulée',       color: 'text-red-600 bg-red-100',       icon: XCircle      },
};

function StatCard({ icon: Icon, label, value, sub, color }) {
  return (
    <div className="card p-5 flex items-center gap-4">
      <div className={`w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ${color}`}>
        <Icon size={22} />
      </div>
      <div>
        <p className="text-xs text-gray-500 font-medium mb-0.5">{label}</p>
        <p className="text-2xl font-bold text-gray-800">{value}</p>
        {sub && <p className="text-xs text-gray-400 mt-0.5">{sub}</p>}
      </div>
    </div>
  );
}

function StatusBadge({ statut }) {
  const cfg = STATUS_CONFIG[statut] || { label: statut, color: 'text-gray-600 bg-gray-100', icon: AlertCircle };
  const Icon = cfg.icon;
  return (
    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${cfg.color}`}>
      <Icon size={11} /> {cfg.label}
    </span>
  );
}

// Mini bar chart textuel
function BarRow({ label, value, max, suffix = '€' }) {
  const pct = max > 0 ? Math.round((value / max) * 100) : 0;
  return (
    <div className="flex items-center gap-3">
      <span className="w-24 text-xs text-gray-600 truncate shrink-0">{label}</span>
      <div className="flex-1 h-2 bg-secondary-300 rounded-full overflow-hidden">
        <div className="h-full bg-primary rounded-full" style={{ width: `${pct}%` }} />
      </div>
      <span className="text-xs font-semibold text-primary w-20 text-right shrink-0">
        {Number(value).toFixed(0)} {suffix}
      </span>
    </div>
  );
}

export default function BuyerStatsPage() {
  const { user } = useAuth();

  const { data, isLoading, isError } = useQuery({
    queryKey: ['acheteur-stats'],
    queryFn: async () => {
      const { data } = await dashboardAPI.getAcheteurStats();
      return data.data ?? data;
    },
    staleTime: 1000 * 60 * 3,
    retry: 1,
  });

  if (isLoading) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-12">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          {Array.from({ length: 4 }).map((_, i) => <div key={i} className="card p-5 h-24 animate-pulse" />)}
        </div>
        <div className="grid lg:grid-cols-2 gap-6">
          {Array.from({ length: 2 }).map((_, i) => <div key={i} className="card p-6 h-64 animate-pulse" />)}
        </div>
      </div>
    );
  }

  if (isError || !data) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-12 text-center">
        <p className="text-gray-500 mb-4">Impossible de charger vos statistiques.</p>
        <Link to="/" className="btn-primary">Retour à l'accueil</Link>
      </div>
    );
  }

  const maxDepense = Math.max(...(data.depenses_mois?.map((d) => d.total) || [1]), 1);
  const maxCat     = Math.max(...(data.categories_pref?.map((c) => c.total_depense) || [1]), 1);

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 py-10">
      {/* Header */}
      <div className="mb-8">
        <h1 className="section-title">Mes statistiques</h1>
        <p className="text-gray-500">Bonjour, {user?.prenom} ! Voici un résumé de votre activité sur MarketCraft.</p>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <StatCard
          icon={TrendingDown} label="Total dépensé"
          value={`${Number(data.total_depense || 0).toFixed(2)} €`}
          color="bg-primary-100 text-primary"
          sub="Hors annulations"
        />
        <StatCard
          icon={ShoppingBag} label="Commandes"
          value={data.nb_commandes || 0}
          color="bg-blue-100 text-blue-600"
        />
        <StatCard
          icon={TrendingUp} label="Panier moyen"
          value={`${Number(data.panier_moyen || 0).toFixed(2)} €`}
          color="bg-green-100 text-green-600"
          sub="Par commande"
        />
        <StatCard
          icon={Star} label="Avis déposés"
          value={data.nb_avis || 0}
          color="bg-amber-100 text-amber-600"
        />
      </div>

      <div className="grid lg:grid-cols-2 gap-6 mb-6">
        {/* Dépenses par mois */}
        <div className="card p-6">
          <h2 className="font-serif font-bold text-lg text-gray-800 mb-5">Dépenses — 6 derniers mois</h2>
          {data.depenses_mois?.length > 0 ? (
            <div className="space-y-4">
              {data.depenses_mois.map((d) => (
                <BarRow
                  key={d.mois}
                  label={new Date(d.mois + '-01').toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' })}
                  value={d.total}
                  max={maxDepense}
                />
              ))}
            </div>
          ) : (
            <p className="text-gray-400 text-sm text-center py-8">Pas encore de dépenses à afficher.</p>
          )}
        </div>

        {/* Catégories préférées */}
        <div className="card p-6">
          <h2 className="font-serif font-bold text-lg text-gray-800 mb-5">Catégories préférées</h2>
          {data.categories_pref?.length > 0 ? (
            <div className="space-y-4">
              {data.categories_pref.map((cat) => (
                <BarRow
                  key={cat.categorie}
                  label={cat.categorie || 'Non catégorisé'}
                  value={cat.total_depense}
                  max={maxCat}
                />
              ))}
            </div>
          ) : (
            <p className="text-gray-400 text-sm text-center py-8">Aucune catégorie.</p>
          )}
        </div>
      </div>

      {/* Historique des commandes */}
      <div className="card p-6">
        <h2 className="font-serif font-bold text-lg text-gray-800 mb-4">Historique des commandes</h2>
        {data.commandes?.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left border-b border-secondary-200">
                  <th className="pb-3 font-semibold text-gray-600">N°</th>
                  <th className="pb-3 font-semibold text-gray-600">Date</th>
                  <th className="pb-3 font-semibold text-gray-600">Articles</th>
                  <th className="pb-3 font-semibold text-gray-600">Total</th>
                  <th className="pb-3 font-semibold text-gray-600">Statut</th>
                  <th className="pb-3 font-semibold text-gray-600">Détail</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-secondary-100">
                {data.commandes.map((order) => (
                  <tr key={order.id} className="hover:bg-secondary-50 transition-colors">
                    <td className="py-3 font-mono text-gray-600">#{order.id}</td>
                    <td className="py-3 text-gray-500">{new Date(order.created_at).toLocaleDateString('fr-FR')}</td>
                    <td className="py-3 text-gray-600">{order.nb_articles}</td>
                    <td className="py-3 font-semibold text-primary">{Number(order.montant_total).toFixed(2)} €</td>
                    <td className="py-3"><StatusBadge statut={order.statut} /></td>
                    <td className="py-3">
                      <Link to={`/commandes/${order.id}`} className="text-xs text-primary hover:underline font-medium">
                        Voir →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="text-center py-10">
            <ShoppingBag size={40} className="mx-auto text-gray-300 mb-3" />
            <p className="text-gray-500 mb-4">Vous n'avez pas encore passé de commande.</p>
            <Link to="/produits" className="btn-primary inline-flex">Explorer les produits</Link>
          </div>
        )}
      </div>
    </div>
  );
}

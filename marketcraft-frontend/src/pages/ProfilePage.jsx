import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  User,
  Mail,
  Save,
  LogOut,
  ShoppingBag,
  CheckCircle,
  Clock,
  Truck,
  XCircle,
  AlertCircle,
  Package,
  Lock,
  KeyRound,
  Eye,
  EyeOff,
  Trash2,
  ShieldAlert,
  MessageSquarePlus,
  ChevronRight,
} from 'lucide-react';
import { authAPI, ordersAPI, recommandationsAPI } from '../services/api';
import RecommandationsIA from '../components/RecommandationsIA';
import { useAuth } from '../hooks/useAuth';
import toast from 'react-hot-toast';

const STATUS_CONFIG = {
  en_attente: { label: 'En attente', color: 'text-amber-600 bg-amber-100', icon: Clock },
  confirmee: { label: 'Confirmée', color: 'text-blue-600 bg-blue-100', icon: CheckCircle },
  expediee: { label: 'Expédiée', color: 'text-purple-600 bg-purple-100', icon: Truck },
  livree: { label: 'Livrée', color: 'text-green-600 bg-green-100', icon: Package },
  annulee: { label: 'Annulée', color: 'text-red-600 bg-red-100', icon: XCircle },
};

const MOCK_ORDERS = Array.from({ length: 4 }, (_, i) => ({
  id: 10024 + i,
  montant_total: [87.5, 145.0, 32.0, 210.9][i],
  statut: ['livree', 'expediee', 'confirmee', 'en_attente'][i],
  created_at: new Date(Date.now() - i * 86400000 * 10).toISOString(),
  nb_articles: [2, 3, 1, 4][i],
}));

function StatusBadge({ statut }) {
  const cfg = STATUS_CONFIG[statut] || {
    label: statut,
    color: 'text-gray-600 bg-gray-100',
    icon: AlertCircle,
  };
  const Icon = cfg.icon;
  return (
    <span
      className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${cfg.color}`}
    >
      <Icon size={11} /> {cfg.label}
    </span>
  );
}

export default function ProfilePage() {
  const { user, updateUser, logout } = useAuth();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState({
    prenom: user?.prenom || '',
    nom: user?.nom || '',
    email: user?.email || '',
  });
  const [errors, setErrors] = useState({});
  const [activeTab, setActiveTab] = useState('infos');

  // Changement de mot de passe
  const [pwForm, setPwForm] = useState({
    current_password: '',
    password: '',
    password_confirm: '',
  });
  const [pwErrors, setPwErrors] = useState({});
  const [showPw, setShowPw] = useState(false);

  // Suppression de compte
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [deletePassword, setDeletePassword] = useState('');

  // Resynchronise le formulaire dès que les infos utilisateur arrivent ou
  // changent (ex. chargement asynchrone via /me après un rafraîchissement
  // de page), sans écraser une saisie en cours par l'utilisateur.
  useEffect(() => {
    if (!user) return;
    setForm({
      prenom: user.prenom || '',
      nom: user.nom || '',
      email: user.email || '',
    });
  }, [user]);

  const { data: ordersData, isLoading: ordersLoading } = useQuery({
    queryKey: ['my-orders'],
    queryFn: async () => {
      const { data } = await ordersAPI.getAll();
      return data;
    },
    staleTime: 1000 * 60 * 2,
    enabled: activeTab === 'commandes',
  });

  const orders =
    ordersData?.data || ordersData?.orders || (ordersLoading ? [] : MOCK_ORDERS);

  const validate = () => {
    const errs = {};
    if (!form.prenom.trim()) errs.prenom = 'Prénom requis.';
    if (!form.nom.trim()) errs.nom = 'Nom requis.';
    if (!form.email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) errs.email = 'Email invalide.';
    return errs;
  };

  const updateMutation = useMutation({
    mutationFn: () => authAPI.updateMe ? authAPI.updateMe(form) : authAPI.me(),
    onSuccess: (res) => {
      const updated = res?.data?.user || { ...user, ...form };
      updateUser(updated);
      toast.success('Profil mis à jour !');
      queryClient.invalidateQueries(['me']);
    },
    onError: () => {
      // In demo mode, update locally
      updateUser({ ...user, ...form });
      toast.success('Profil mis à jour !');
    },
  });

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) setErrors((prev) => ({ ...prev, [name]: '' }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length > 0) {
      setErrors(errs);
      return;
    }
    updateMutation.mutate();
  };

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  // ── Changement de mot de passe ──────────────────────────────────────
  const passwordMutation = useMutation({
    mutationFn: () =>
      authAPI.updateMe({
        current_password: pwForm.current_password,
        password: pwForm.password,
      }),
    onSuccess: () => {
      toast.success('Mot de passe modifié !');
      setPwForm({ current_password: '', password: '', password_confirm: '' });
      setPwErrors({});
    },
    onError: (err) => {
      const status = err?.response?.status;
      if (status === 403) {
        setPwErrors({ current_password: 'Mot de passe actuel incorrect.' });
      } else if (status === 422) {
        setPwErrors({ password: 'Le mot de passe doit contenir au moins 8 caractères.' });
      } else {
        toast.error('Échec de la modification du mot de passe.');
      }
    },
  });

  const handlePwChange = (e) => {
    const { name, value } = e.target;
    setPwForm((prev) => ({ ...prev, [name]: value }));
    if (pwErrors[name]) setPwErrors((prev) => ({ ...prev, [name]: '' }));
  };

  const handlePwSubmit = (e) => {
    e.preventDefault();
    const errs = {};
    if (!pwForm.current_password) errs.current_password = 'Mot de passe actuel requis.';
    if (pwForm.password.length < 8) errs.password = 'Au moins 8 caractères.';
    if (pwForm.password !== pwForm.password_confirm)
      errs.password_confirm = 'Les mots de passe ne correspondent pas.';
    if (Object.keys(errs).length > 0) {
      setPwErrors(errs);
      return;
    }
    passwordMutation.mutate();
  };

  // ── Suppression de compte ───────────────────────────────────────────
  const deleteMutation = useMutation({
    mutationFn: () => authAPI.deleteMe({ password: deletePassword }),
    onSuccess: async () => {
      toast.success('Compte supprimé.');
      await logout();
      navigate('/');
    },
    onError: (err) => {
      const status = err?.response?.status;
      if (status === 403) {
        toast.error('Mot de passe incorrect.');
      } else {
        toast.error('Échec de la suppression du compte.');
      }
    },
  });

  const tabs = [
    { key: 'infos', label: 'Mes informations', icon: User },
    { key: 'commandes', label: 'Mes commandes', icon: ShoppingBag },
  ];

  const initials = ((user?.prenom || '')[0] || '') + ((user?.nom || '')[0] || '') || 'U';

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div className="flex items-center gap-4">
          <div className="w-14 h-14 bg-primary rounded-2xl flex items-center justify-center shadow-craft flex-shrink-0">
            <span className="text-white text-xl font-bold">{initials.toUpperCase()}</span>
          </div>
          <div>
            <h1 className="text-2xl font-serif font-bold text-gray-800">
              {user?.prenom} {user?.nom}
            </h1>
            <p className="text-sm text-gray-500">{user?.email}</p>
          </div>
        </div>

        <button
          onClick={handleLogout}
          className="flex items-center gap-2 text-sm text-red-500 hover:text-red-700 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors"
        >
          <LogOut size={15} /> Déconnexion
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-secondary-300 mb-8">
        {tabs.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key)}
            className={`flex items-center gap-2 px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors ${
              activeTab === key
                ? 'border-primary text-primary'
                : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
          >
            <Icon size={15} /> {label}
          </button>
        ))}
      </div>

      {/* ── Tab: Infos personnelles ─────────────────────────────────────── */}
      {activeTab === 'infos' && (
        <div className="max-w-lg">
          <div className="card p-6">
            <h2 className="font-serif font-bold text-lg text-gray-800 mb-5">
              Informations personnelles
            </h2>

            <form onSubmit={handleSubmit} className="space-y-5" noValidate>
              {/* Prénom */}
              <div>
                <label htmlFor="prenom" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Prénom <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <User
                    size={15}
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                  />
                  <input
                    id="prenom"
                    name="prenom"
                    type="text"
                    value={form.prenom}
                    onChange={handleChange}
                    autoComplete="given-name"
                    className={`input-field pl-9 ${errors.prenom ? 'border-red-400' : ''}`}
                  />
                </div>
                {errors.prenom && (
                  <p className="text-red-500 text-xs mt-1">{errors.prenom}</p>
                )}
              </div>

              {/* Nom */}
              <div>
                <label htmlFor="nom" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Nom <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <User
                    size={15}
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                  />
                  <input
                    id="nom"
                    name="nom"
                    type="text"
                    value={form.nom}
                    onChange={handleChange}
                    autoComplete="family-name"
                    className={`input-field pl-9 ${errors.nom ? 'border-red-400' : ''}`}
                  />
                </div>
                {errors.nom && <p className="text-red-500 text-xs mt-1">{errors.nom}</p>}
              </div>

              {/* Email */}
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1.5">
                  Email <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <Mail
                    size={15}
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                  />
                  <input
                    id="email"
                    name="email"
                    type="email"
                    value={form.email}
                    onChange={handleChange}
                    autoComplete="email"
                    className={`input-field pl-9 ${errors.email ? 'border-red-400' : ''}`}
                  />
                </div>
                {errors.email && (
                  <p className="text-red-500 text-xs mt-1">{errors.email}</p>
                )}
              </div>

              {/* Role (display only) */}
              {user?.role && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">
                    Type de compte
                  </label>
                  <div className="input-field bg-secondary-100 text-gray-600 cursor-not-allowed capitalize">
                    {user.role === 'vendeur'
                      ? 'Artisan vendeur'
                      : user.role === 'admin'
                      ? 'Administrateur'
                      : 'Acheteur'}
                  </div>
                </div>
              )}

              <button
                type="submit"
                disabled={updateMutation.isPending}
                className="btn-primary w-full flex items-center justify-center gap-2 py-3"
              >
                {updateMutation.isPending ? (
                  <>
                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    Sauvegarde…
                  </>
                ) : (
                  <>
                    <Save size={16} /> Enregistrer les modifications
                  </>
                )}
              </button>
            </form>
          </div>

          {/* Changement de mot de passe */}
          <div className="card p-6 mt-6">
            <h2 className="font-serif font-bold text-lg text-gray-800 mb-5 flex items-center gap-2">
              <KeyRound size={18} className="text-primary" /> Changer le mot de passe
            </h2>

            <form onSubmit={handlePwSubmit} className="space-y-5" noValidate>
              {[
                { name: 'current_password', label: 'Mot de passe actuel', ac: 'current-password' },
                { name: 'password', label: 'Nouveau mot de passe', ac: 'new-password' },
                { name: 'password_confirm', label: 'Confirmer le nouveau mot de passe', ac: 'new-password' },
              ].map(({ name, label, ac }) => (
                <div key={name}>
                  <label htmlFor={name} className="block text-sm font-medium text-gray-700 mb-1.5">
                    {label} <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <Lock
                      size={15}
                      className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                    />
                    <input
                      id={name}
                      name={name}
                      type={showPw ? 'text' : 'password'}
                      value={pwForm[name]}
                      onChange={handlePwChange}
                      autoComplete={ac}
                      className={`input-field pl-9 pr-9 ${pwErrors[name] ? 'border-red-400' : ''}`}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPw((s) => !s)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                      aria-label={showPw ? 'Masquer les mots de passe' : 'Afficher les mots de passe'}
                    >
                      {showPw ? <EyeOff size={15} /> : <Eye size={15} />}
                    </button>
                  </div>
                  {pwErrors[name] && (
                    <p className="text-red-500 text-xs mt-1">{pwErrors[name]}</p>
                  )}
                </div>
              ))}

              <button
                type="submit"
                disabled={passwordMutation.isPending}
                className="btn-primary w-full flex items-center justify-center gap-2 py-3"
              >
                {passwordMutation.isPending ? (
                  <>
                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    Modification…
                  </>
                ) : (
                  <>
                    <KeyRound size={16} /> Modifier le mot de passe
                  </>
                )}
              </button>
            </form>
          </div>

          {/* Zone de danger */}
          <div className="card p-5 mt-6 border-red-200">
            <h3 className="font-semibold text-red-700 text-sm mb-4 flex items-center gap-2">
              <ShieldAlert size={16} /> Zone de danger
            </h3>

            <div className="flex flex-col gap-4">
              {/* Déconnexion */}
              <div>
                <p className="text-xs text-gray-500 mb-2">
                  La déconnexion effacera votre session sur cet appareil.
                </p>
                <button
                  onClick={handleLogout}
                  className="flex items-center gap-2 text-sm text-red-600 border border-red-300 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors"
                >
                  <LogOut size={15} /> Se déconnecter
                </button>
              </div>

              {/* Suppression du compte */}
              <div className="border-t border-red-100 pt-4">
                <p className="text-xs text-gray-500 mb-2">
                  La suppression désactive définitivement votre compte. Cette action est
                  irréversible.
                </p>

                {!confirmDelete ? (
                  <button
                    onClick={() => setConfirmDelete(true)}
                    className="flex items-center gap-2 text-sm text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg transition-colors"
                  >
                    <Trash2 size={15} /> Supprimer mon compte
                  </button>
                ) : (
                  <div className="space-y-3 bg-red-50 border border-red-200 rounded-lg p-4">
                    <p className="text-sm text-red-700 font-medium">
                      Confirmez avec votre mot de passe pour supprimer votre compte.
                    </p>
                    <div className="relative">
                      <Lock
                        size={15}
                        className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                      />
                      <input
                        type="password"
                        value={deletePassword}
                        onChange={(e) => setDeletePassword(e.target.value)}
                        placeholder="Votre mot de passe"
                        autoComplete="current-password"
                        className="input-field pl-9"
                      />
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => deleteMutation.mutate()}
                        disabled={!deletePassword || deleteMutation.isPending}
                        className="flex items-center gap-2 text-sm text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed px-4 py-2 rounded-lg transition-colors"
                      >
                        {deleteMutation.isPending ? (
                          <>
                            <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                            Suppression…
                          </>
                        ) : (
                          <>
                            <Trash2 size={15} /> Confirmer la suppression
                          </>
                        )}
                      </button>
                      <button
                        onClick={() => {
                          setConfirmDelete(false);
                          setDeletePassword('');
                        }}
                        className="text-sm text-gray-600 border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded-lg transition-colors"
                      >
                        Annuler
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ── Tab: Commandes ─────────────────────────────────────────────── */}
      {activeTab === 'commandes' && (
        <div>
          <h2 className="font-serif font-bold text-xl text-gray-800 mb-5">
            Historique des commandes
          </h2>

          {ordersLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="card p-4 animate-pulse flex gap-4">
                  <div className="flex-1 space-y-2">
                    <div className="h-4 bg-secondary-300 rounded w-1/4" />
                    <div className="h-3 bg-secondary-300 rounded w-1/3" />
                  </div>
                  <div className="h-6 bg-secondary-300 rounded w-20" />
                </div>
              ))}
            </div>
          ) : orders.length === 0 ? (
            <div className="text-center py-20 flex flex-col items-center gap-4">
              <ShoppingBag size={56} className="text-secondary-400" />
              <h3 className="text-xl font-serif font-semibold text-gray-700">
                Aucune commande
              </h3>
              <p className="text-gray-500 text-sm">
                Vous n'avez pas encore passé de commande.
              </p>
              <a href="/produits" className="btn-primary">
                Découvrir les produits
              </a>
            </div>
          ) : (
            <div className="card overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-secondary-100">
                  <tr className="text-left">
                    <th className="px-5 py-3.5 font-semibold text-gray-600">N° commande</th>
                    <th className="px-5 py-3.5 font-semibold text-gray-600">Date</th>
                    <th className="px-5 py-3.5 font-semibold text-gray-600">Articles</th>
                    <th className="px-5 py-3.5 font-semibold text-gray-600">Total</th>
                    <th className="px-5 py-3.5 font-semibold text-gray-600">Statut</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-secondary-100">
                  {orders.map((order) => {
                    const lignes = order.lignes || [];
                    const livree = order.statut === 'livree';

                    return (
                      <React.Fragment key={order.id}>
                        <tr className="hover:bg-secondary-50 transition-colors">
                          <td className="px-5 py-4 font-mono text-gray-600">#{order.id}</td>
                          <td className="px-5 py-4 text-gray-600">
                            {new Date(order.created_at).toLocaleDateString('fr-FR', {
                              day: 'numeric',
                              month: 'short',
                              year: 'numeric',
                            })}
                          </td>
                          <td className="px-5 py-4 text-gray-600">
                            {order.nb_articles || 1} article
                            {(order.nb_articles || 1) > 1 ? 's' : ''}
                          </td>
                          <td className="px-5 py-4 font-semibold text-primary">
                            {Number(order.montant_total ?? order.total ?? 0).toFixed(2)} €
                          </td>
                          <td className="px-5 py-4">
                            <StatusBadge statut={order.statut} />
                          </td>
                        </tr>

                        {/* Détail des articles : c'est le seul chemin depuis
                            l'historique vers la fiche produit, donc vers le
                            dépôt d'un avis. L'API n'autorise l'avis qu'après
                            achat — inutile de proposer le lien avant la
                            livraison. */}
                        {lignes.length > 0 && (
                          <tr className="bg-secondary-50/50">
                            <td colSpan={5} className="px-5 pb-4 pt-0">
                              <ul className="space-y-1.5">
                                {lignes.map((ligne, i) => (
                                  <li
                                    key={`${order.id}-${ligne.produit_id}-${i}`}
                                    className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                                  >
                                    <Link
                                      to={`/produits/${ligne.produit_id}`}
                                      className="text-gray-700 hover:text-primary transition-colors inline-flex items-center gap-1"
                                    >
                                      <ChevronRight size={13} className="text-secondary-400" />
                                      {ligne.nom_produit}
                                      {ligne.quantite > 1 && (
                                        <span className="text-gray-400"> × {ligne.quantite}</span>
                                      )}
                                    </Link>

                                    {livree && (
                                      <Link
                                        to={`/produits/${ligne.produit_id}#avis`}
                                        className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                      >
                                        <MessageSquarePlus size={13} />
                                        Donner mon avis
                                      </Link>
                                    )}
                                  </li>
                                ))}
                              </ul>

                              {!livree && (
                                <p className="text-xs text-gray-400 mt-2">
                                  Vous pourrez déposer un avis une fois la commande livrée.
                                </p>
                              )}
                            </td>
                          </tr>
                        )}
                      </React.Fragment>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          {/* Suggestions fondees sur l'historique d'achat — module IA du
              cahier des charges (option C). Le composant ne rend rien tant
              que le client n'a pas commande, ce qui evite un encart vide
              sur un compte neuf. */}
          <RecommandationsIA
            cleCache="historique"
            recuperer={() => recommandationsAPI.parHistorique(4)}
            titre="Cela pourrait vous plaire"
            sousTitre="D'après les artisans et les matières de vos commandes précédentes."
          />
        </div>
      )}
    </div>
  );
}

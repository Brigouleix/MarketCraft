<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marque comme deja appliquees les migrations dont la table existe deja.
 *
 * Le schema de MarketCraft precede ce projet : il a ete cree a la main par
 * l'ancien back-end. Rejouer les migrations dessus echoue (« table already
 * exists »), et les rejouer apres un migrate:fresh detruirait les donnees
 * de demonstration, qui sont un livrable.
 *
 * Cette commande reconcilie les deux : elle inscrit dans la table
 * `migrations` celles dont la table cible est deja presente, et laisse les
 * autres a `php artisan migrate`. Les migrations qui ne creent pas de table
 * — les composants SGBD, par exemple — sont toujours laissees en attente :
 * elles sont ecrites pour etre rejouables.
 *
 * A n'utiliser qu'une fois, sur une base preexistante. Sur une base vierge,
 * `php artisan migrate` suffit.
 */
class AdopterBaseExistante extends Command
{
    protected $signature = 'db:adopter
                            {--dry-run : Affiche ce qui serait fait, sans rien ecrire}';

    protected $description = 'Marque comme appliquees les migrations dont la table existe deja';

    public function handle(): int
    {
        if (! Schema::hasTable('migrations')) {
            $this->error('Table `migrations` absente. Lancez d abord : php artisan migrate:install');

            return self::FAILURE;
        }

        $deja = DB::table('migrations')->pluck('migration')->all();
        $lot  = (int) DB::table('migrations')->max('batch') + 1;

        $aMarquer = [];
        $aJouer   = [];

        foreach ($this->fichiersDeMigration() as $nom) {
            if (in_array($nom, $deja, true)) {
                continue;
            }

            $table = $this->tableCible($nom);

            if ($table !== null && Schema::hasTable($table)) {
                $aMarquer[] = [$nom, $table];
            } else {
                $aJouer[] = [$nom, $table ?? '—'];
            }
        }

        if ($aMarquer === [] && $aJouer === []) {
            $this->info('Rien a faire : toutes les migrations sont deja enregistrees.');

            return self::SUCCESS;
        }

        if ($aMarquer !== []) {
            $this->line('');
            $this->info('Marquees comme deja appliquees (la table existe) :');
            $this->table(['Migration', 'Table'], $aMarquer);
        }

        if ($aJouer !== []) {
            $this->line('');
            $this->comment('Laissees en attente, a executer par `php artisan migrate` :');
            $this->table(['Migration', 'Table'], $aJouer);
        }

        if ($this->option('dry-run')) {
            $this->line('');
            $this->comment('--dry-run : rien n a ete ecrit.');

            return self::SUCCESS;
        }

        if ($aMarquer === []) {
            $this->line('');
            $this->info('Aucune migration a marquer. Lancez : php artisan migrate');

            return self::SUCCESS;
        }

        $this->line('');

        if (! $this->confirm('Inscrire ces migrations dans la table `migrations` ?', true)) {
            $this->comment('Abandon.');

            return self::SUCCESS;
        }

        DB::table('migrations')->insert(array_map(
            static fn (array $ligne) => ['migration' => $ligne[0], 'batch' => $lot],
            $aMarquer
        ));

        $this->info(count($aMarquer) . ' migration(s) enregistree(s) dans le lot ' . $lot . '.');
        $this->line('Etape suivante : php artisan migrate');

        return self::SUCCESS;
    }

    /**
     * @return string[]  Noms de migration, sans extension, dans l'ordre.
     */
    private function fichiersDeMigration(): array
    {
        $fichiers = glob(database_path('migrations/*.php')) ?: [];

        sort($fichiers);

        return array_map(
            static fn (string $chemin) => basename($chemin, '.php'),
            $fichiers
        );
    }

    /**
     * Deduit la table visee depuis le nom du fichier
     * (« ..._create_utilisateurs_table » -> « utilisateurs »).
     *
     * Retourne null si le nom ne suit pas cette convention : la migration
     * est alors laissee en attente, ce qui est le choix prudent.
     */
    private function tableCible(string $migration): ?string
    {
        return preg_match('/create_(.+?)_table$/', $migration, $m) === 1
            ? $m[1]
            : null;
    }
}

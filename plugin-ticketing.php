<?php
/*
Plugin Name: Réservation et Ticketing
Description: Plugin pour gérer les réservations, entreprises, services et utilisateurs
Version: 1.3
Author: Votre Nom
*/

// Empêcher l'accès direct au fichier
if (!defined('ABSPATH')) {
    exit;
}

class ReservationTicketingPlugin {
    private $version = '1.3';

    public function __construct() {
        // Hooks d'activation et de désactivation du plugin
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialisation 
        add_action('init', array($this, 'init_plugin'));
        
        // Ajout des menus d'administration
        add_action('admin_menu', array($this, 'add_admin_menus'));

        // Gestion des actions de formulaire
        add_action('admin_post_ajouter_entreprise', array($this, 'handle_ajouter_entreprise'));
        add_action('admin_post_ajouter_service', array($this, 'handle_ajouter_service'));

        // Ajout des nouveaux hooks de suppression
        add_action('admin_post_supprimer_entreprise', array($this, 'handle_supprimer_entreprise'));
        add_action('admin_post_supprimer_service', array($this, 'handle_supprimer_service'));
    }

    public function init_plugin() {
        // Vérification de la version du plugin
        $installed_version = get_option('reservation_ticketing_version');
        if ($installed_version !== $this->version) {
            $this->activate();
            update_option('reservation_ticketing_version', $this->version);
        }
    }

    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Inclusion nécessaire pour dbDelta()
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table pour les entreprises
        $entreprises_table = $wpdb->prefix . 'reservation_entreprises';
        $sql_entreprises = "CREATE TABLE IF NOT EXISTS $entreprises_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nom varchar(100) NOT NULL,
            description text,
            email varchar(100),
            telephone varchar(20),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Table pour les services
        $services_table = $wpdb->prefix . 'reservation_services';
        $sql_services = "CREATE TABLE IF NOT EXISTS $services_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            entreprise_id mediumint(9) NOT NULL,
            nom varchar(100) NOT NULL,
            description text,
            duree int(11),
            prix decimal(10,2),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Table pour les réservations
        $reservations_table = $wpdb->prefix . 'reservation_rendez_vous';
        $sql_reservations = "CREATE TABLE IF NOT EXISTS $reservations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            service_id mediumint(9) NOT NULL,
            utilisateur_id bigint(20) NOT NULL,
            date_reservation datetime NOT NULL,
            statut varchar(20) DEFAULT 'en_attente',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Exécution des requêtes
        $wpdb->query($sql_entreprises);
        $wpdb->query($sql_services);
        $wpdb->query($sql_reservations);
    }

    public function deactivate() {
        // Option de nettoyage si nécessaire
        delete_option('reservation_ticketing_version');
    }

    public function add_admin_menus() {
        // Menu principal pour les réservations
        add_menu_page(
            'Réservations', 
            'Réservations', 
            'manage_options', 
            'reservations_ticketing', 
            array($this, 'render_main_page'),
            'dashicons-calendar-alt', 
            6
        );

        // Sous-menu pour gérer les entreprises
        add_submenu_page(
            'reservations_ticketing', 
            'Entreprises', 
            'Entreprises', 
            'manage_options', 
            'reservations_entreprises', 
            array($this, 'render_entreprises_page')
        );

        // Sous-menu pour gérer les services
        add_submenu_page(
            'reservations_ticketing', 
            'Services', 
            'Services', 
            'manage_options', 
            'reservations_services', 
            array($this, 'render_services_page')
        );
    }

    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1>Réservations et Ticketing</h1>
            <p>Bienvenue dans votre système de gestion de réservations.</p>
        </div>
        <?php
    }

    public function render_entreprises_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservation_entreprises';
        $entreprises = $wpdb->get_results("SELECT * FROM $table_name");
        
        ?>
        <div class="wrap">
            <h1>Entreprises</h1>
            
            <!-- Formulaire d'ajout d'entreprise -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Ajouter une nouvelle entreprise</h2>
                </div>
                <div class="inside">
                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                        <?php wp_nonce_field('ajouter_entreprise_nonce'); ?>
                        <input type="hidden" name="action" value="ajouter_entreprise">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="nom_entreprise">Nom de l'entreprise</label></th>
                                <td><input type="text" name="nom_entreprise" id="nom_entreprise" required class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="description_entreprise">Description</label></th>
                                <td><textarea name="description_entreprise" id="description_entreprise" rows="4" class="regular-text"></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="email_entreprise">Email</label></th>
                                <td><input type="email" name="email_entreprise" id="email_entreprise" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="telephone_entreprise">Téléphone</label></th>
                                <td><input type="tel" name="telephone_entreprise" id="telephone_entreprise" class="regular-text"></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Ajouter l'entreprise">
                        </p>
                    </form>
                </div>
            </div>

            <!-- Liste des entreprises -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($entreprises as $entreprise): ?>
                    <tr>
                        <td><?php echo esc_html($entreprise->id); ?></td>
                        <td><?php echo esc_html($entreprise->nom); ?></td>
                        <td><?php echo esc_html($entreprise->email); ?></td>
                        <td><?php echo esc_html($entreprise->telephone); ?></td>
                        <td>
                            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" 
                                  onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette entreprise ?');">
                                <?php wp_nonce_field('supprimer_entreprise_nonce'); ?>
                                <input type="hidden" name="action" value="supprimer_entreprise">
                                <input type="hidden" name="entreprise_id" value="<?php echo esc_attr($entreprise->id); ?>">
                                <input type="submit" value="Supprimer" class="button button-secondary">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_services_page() {
        global $wpdb;
        $services_table = $wpdb->prefix . 'reservation_services';
        $entreprises_table = $wpdb->prefix . 'reservation_entreprises';
        
        // Récupération des services avec le nom de l'entreprise
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, e.nom as nom_entreprise 
            FROM $services_table s
            LEFT JOIN $entreprises_table e ON s.entreprise_id = e.id"
        ));

        // Récupération des entreprises pour le formulaire
        $entreprises = $wpdb->get_results("SELECT * FROM $entreprises_table");
        
        ?>
        <div class="wrap">
            <h1>Services</h1>
            
            <!-- Formulaire d'ajout de service -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Ajouter un nouveau service</h2>
                </div>
                <div class="inside">
                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                        <?php wp_nonce_field('ajouter_service_nonce'); ?>
                        <input type="hidden" name="action" value="ajouter_service">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="nom_service">Nom du service</label></th>
                                <td><input type="text" name="nom_service" id="nom_service" required class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="description_service">Description</label></th>
                                <td><textarea name="description_service" id="description_service" rows="4" class="regular-text"></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="entreprise_id">Entreprise</label></th>
                                <td>
                                    <select name="entreprise_id" id="entreprise_id" required>
                                        <option value="">Sélectionnez une entreprise</option>
                                        <?php foreach($entreprises as $entreprise): ?>
                                            <option value="<?php echo esc_attr($entreprise->id); ?>">
                                                <?php echo esc_html($entreprise->nom); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="duree_service">Durée (minutes)</label></th>
                                <td><input type="number" name="duree_service" id="duree_service" required class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="prix_service">Prix (€)</label></th>
                                <td><input type="number" step="0.01" name="prix_service" id="prix_service" required class="regular-text"></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Ajouter le service">
                        </p>
                    </form>
                </div>
            </div>

            <!-- Liste des services -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Entreprise</th>
                        <th>Durée</th>
                        <th>Prix</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($services as $service): ?>
                    <tr>
                        <td><?php echo esc_html($service->id); ?></td>
                        <td><?php echo esc_html($service->nom); ?></td>
                        <td><?php echo esc_html($service->nom_entreprise); ?></td>
                        <td><?php echo esc_html($service->duree); ?> min</td>
                        <td><?php echo esc_html(number_format($service->prix, 2)); ?> €</td>
                        <td>
                            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" 
                                  onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');">
                                <?php wp_nonce_field('supprimer_service_nonce'); ?>
                                <input type="hidden" name="action" value="supprimer_service">
                                <input type="hidden" name="service_id" value="<?php echo esc_attr($service->id); ?>">
                                <input type="submit" value="Supprimer" class="button button-secondary">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_ajouter_entreprise() {
        // Vérification du nonce
        check_admin_referer('ajouter_entreprise_nonce');

        // Vérification des permissions
        if (!current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires.');
        }

        // Récupération et assainissement des données
        $nom = sanitize_text_field($_POST['nom_entreprise']);
        $description = sanitize_textarea_field($_POST['description_entreprise']);
        $email = sanitize_email($_POST['email_entreprise']);
        $telephone = sanitize_text_field($_POST['telephone_entreprise']);

        // Validation des données
        if (empty($nom)) {
            wp_die('Le nom de l\'entreprise est obligatoire.');
        }

        // Ajout de l'entreprise
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservation_entreprises';
        
        $resultat = $wpdb->insert(
            $table_name,
            array(
                'nom' => $nom,
                'description' => $description,
                'email' => $email,
                'telephone' => $telephone
            ),
            array('%s', '%s', '%s', '%s')
        );

        // Redirection avec message de succès ou d'erreur
        if ($resultat) {
            wp_redirect(admin_url('admin.php?page=reservations_entreprises&message=entreprise_added'));
        } else {
            wp_redirect(admin_url('admin.php?page=reservations_entreprises&message=entreprise_error'));
        }
        exit();
    }

    public function handle_ajouter_service() {
        // Vérification du nonce
        check_admin_referer('ajouter_service_nonce');

        // Vérification des permissions
        if (!current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires.');
        }

        // Récupération et assainissement des données
        $nom = sanitize_text_field($_POST['nom_service']);
        $description = sanitize_textarea_field($_POST['description_service']);
        $entreprise_id = intval($_POST['entreprise_id']);
        $duree = intval($_POST['duree_service']);
        $prix = floatval($_POST['prix_service']);

        // Validation des données
        if (empty($nom) || empty($entreprise_id)) {
            wp_die('Veuillez remplir tous les champs obligatoires.');
        }

        // Ajout du service
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservation_services';
        
        $resultat = $wpdb->insert(
            $table_name,
            array(
                'entreprise_id' => $entreprise_id,
                'nom' => $nom,
                'description' => $description,
                'duree' => $duree,
                'prix' => $prix
            ),
            array('%d', '%s', '%s', '%d', '%f')
        );

        // Redirection avec message de succès ou d'erreur
        if ($resultat) {
            wp_redirect(admin_url('admin.php?page=reservations_services&message=service_added'));
        } else {
            wp_redirect(admin_url('admin.php?page=reservations_services&message=service_error'));
        }
        exit();
    }

    public function handle_supprimer_entreprise() {
        // Vérification du nonce
        check_admin_referer('supprimer_entreprise_nonce');

        // Vérification des permissions
        if (!current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires.');
        }

        // Récupération de l'ID de l'entreprise
        $entreprise_id = intval($_POST['entreprise_id']);

        // Vérification que l'entreprise n'a pas de services associés
        global $wpdb;
        $services_table = $wpdb->prefix . 'reservation_services';
        $services_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $services_table WHERE entreprise_id = %d", 
            $entreprise_id
        ));

        if ($services_count > 0) {
            wp_die('Impossible de supprimer cette entreprise car elle a des services associés.');
        }

        // Suppression de l'entreprise
        $table_name = $wpdb->prefix . 'reservation_entreprises';
        $resultat = $wpdb->delete(
            $table_name,
            array('id' => $entreprise_id),
            array('%d')
        );

        // Redirection avec message de succès ou d'erreur
        if ($resultat) {
            wp_redirect(admin_url('admin.php?page=reservations_entreprises&message=entreprise_deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=reservations_entreprises&message=entreprise_delete_error'));
        }
        exit();
    }

    public function handle_supprimer_service() {
        // Vérification du nonce
        check_admin_referer('supprimer_service_nonce');

        // Vérification des permissions
        if (!current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires.');
        }

        // Récupération de l'ID du service
        $service_id = intval($_POST['service_id']);

        // Vérification que le service n'a pas de réservations associées
        global $wpdb;
        $reservations_table = $wpdb->prefix . 'reservation_rendez_vous';
        $reservations_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $reservations_table WHERE service_id = %d", 
            $service_id
        ));

        if ($reservations_count > 0) {
            wp_die('Impossible de supprimer ce service car il a des réservations associées.');
        }

        // Suppression du service
        $services_table = $wpdb->prefix . 'reservation_services';
        $resultat = $wpdb->delete(
            $services_table,
            array('id' => $service_id),
            array('%d')
        );

        // Redirection avec message de succès ou d'erreur
        if ($resultat) {
            wp_redirect(admin_url('admin.php?page=reservations_services&message=service_deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=reservations_services&message=service_delete_error'));
        }
        exit();
    }
}

// Initialisation du plugin
function initialiser_reservation_ticketing() {
    new ReservationTicketingPlugin();
}
add_action('plugins_loaded', 'initialiser_reservation_ticketing');
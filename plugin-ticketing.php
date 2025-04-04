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

        //hooks de reservation
        add_action('wp_ajax_charger_services_entreprise', array($this, 'charger_services_entreprise'));
        add_action('admin_post_ajouter_reservation', array($this, 'handle_ajouter_reservation'));
        add_action('admin_post_supprimer_reservation', array($this, 'handle_supprimer_reservation'));
        add_action('admin_post_modifier_statut_reservation', array($this, 'handle_modifier_statut_reservation'));
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

        // Sous-menu pour gérer les réservations
        add_submenu_page(
            'reservations_ticketing', 
            'Liste des Réservations', 
            'Liste des Réservations', 
            'manage_options', 
            'reservations_liste', 
            array($this, 'render_reservations_page')
        );
    }

    public function render_main_page() {
        global $wpdb;
        $entreprises_table = $wpdb->prefix . 'reservation_entreprises';
        $services_table = $wpdb->prefix . 'reservation_services';
    
        // Récupérer la liste des entreprises
        $entreprises = $wpdb->get_results("SELECT * FROM $entreprises_table");
        ?>
        <div class="wrap">
            <h1>Réservation de service</h1>
            
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Formulaire de réservation</h2>
                </div>
                <div class="inside">
                    <form id="reservation-form" action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                        <?php wp_nonce_field('ajouter_reservation_nonce'); ?>
                        <input type="hidden" name="action" value="ajouter_reservation">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="entreprise_id">Entreprise</label></th>
                                <td>
                                    <select name="entreprise_id" id="entreprise_id" required class="regular-text">
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
                                <th><label for="service_id">Service</label></th>
                                <td>
                                    <select name="service_id" id="service_id" required class="regular-text" disabled>
                                        <option value="">Sélectionnez d'abord une entreprise</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="date_reservation">Date de réservation</label></th>
                                <td>
                                    <input type="datetime-local" name="date_reservation" id="date_reservation" required class="regular-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th><label for="utilisateur_id">Utilisateur</label></th>
                                <td>
                                    <?php 
                                    // Récupérer les utilisateurs WordPress
                                    $current_user = wp_get_current_user(); 
                                    ?>
                                    <input type="text" value="<?php echo esc_attr($current_user->display_name); ?>" readonly class="regular-text">
                                    <input type="hidden" name="utilisateur_id" value="<?php echo esc_attr($current_user->ID); ?>">
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Réserver" disabled>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    
        <script>
        jQuery(document).ready(function($) {
            $('#entreprise_id').on('change', function() {
                var entrepriseId = $(this).val();
                var serviceSelect = $('#service_id');
                var submitButton = $('#submit');
    
                // Réinitialiser le service
                serviceSelect.html('<option value="">Chargement des services...</option>').prop('disabled', true);
                submitButton.prop('disabled', true);
    
                // Requête AJAX pour charger les services de l'entreprise
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        'action': 'charger_services_entreprise',
                        'entreprise_id': entrepriseId
                    },
                    success: function(response) {
                        if (response.success) {
                            serviceSelect.html(response.data);
                            serviceSelect.prop('disabled', false);
                        } else {
                            serviceSelect.html('<option value="">Aucun service disponible</option>');
                        }
                    }
                });
            });
    
            // Activer le bouton de soumission quand un service est sélectionné
            $('#service_id').on('change', function() {
                $('#submit').prop('disabled', $(this).val() === '');
            });
        });
        </script>
        <?php
    }
    
    // Fonction AJAX pour charger les services d'une entreprise
    public function charger_services_entreprise() {
        global $wpdb;
        $services_table = $wpdb->prefix . 'reservation_services';
        $entreprise_id = intval($_POST['entreprise_id']);
    
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nom, duree, prix FROM $services_table WHERE entreprise_id = %d",
            $entreprise_id
        ));
    
        $options = '<option value="">Sélectionnez un service</option>';
        foreach ($services as $service) {
            $options .= sprintf(
                '<option value="%d">%s (Durée: %d min, Prix: %.2f €)</option>',
                $service->id, 
                esc_html($service->nom), 
                $service->duree, 
                $service->prix
            );
        }
    
        wp_send_json_success($options);
    }
    
    // Méthode pour gérer l'ajout de réservation
    public function handle_ajouter_reservation() {
        // Vérification du nonce
        check_admin_referer('ajouter_reservation_nonce');
    
        // Vérification des permissions - ici on vérifie simplement si l'utilisateur est connecté
        if (!is_user_logged_in()) {
            wp_die('Vous devez être connecté pour effectuer une réservation.');
        }
    
        // Récupération et assainissement des données
        $service_id = intval($_POST['service_id']);
        $utilisateur_id = get_current_user_id();
        $date_reservation = sanitize_text_field($_POST['date_reservation']);
    
        // Validation des données
        if (empty($service_id) || empty($date_reservation)) {
            wp_die('Veuillez remplir tous les champs obligatoires.');
        }
    
        // Ajout de la réservation
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservation_rendez_vous';
        
        $resultat = $wpdb->insert(
            $table_name,
            array(
                'service_id' => $service_id,
                'utilisateur_id' => $utilisateur_id,
                'date_reservation' => $date_reservation,
                'statut' => 'en_attente'
            ),
            array('%d', '%d', '%s', '%s')
        );
    
        // Redirection avec message de succès ou d'erreur
        if ($resultat) {
            wp_redirect(admin_url('admin.php?page=reservations_ticketing&message=reservation_added'));
        } else {
            wp_redirect(admin_url('admin.php?page=reservations_ticketing&message=reservation_error'));
        }
        exit();
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

    public function render_reservations_page() {
        global $wpdb;
        $reservations_table = $wpdb->prefix . 'reservation_rendez_vous';
        $services_table = $wpdb->prefix . 'reservation_services';
    
        // Requête modifiée pour récupérer les réservations avec jointure
        $query = $wpdb->prepare(
            "SELECT r.id, r.date_reservation, r.statut, 
                    s.nom as service_nom, 
                    r.utilisateur_id
             FROM $reservations_table r
             LEFT JOIN $services_table s ON r.service_id = s.id
             ORDER BY r.date_reservation DESC"
        );
        $reservations = $wpdb->get_results($query);
    
        // Débogage
        if ($wpdb->last_error) {
            echo "<div class='error'><p>Erreur de base de données : " . esc_html($wpdb->last_error) . "</p></div>";
        }
    
        ?>
        <div class="wrap">
            <h1>Liste des Réservations</h1>
            
            <?php if (empty($reservations)): ?>
                <div class="notice notice-warning">
                    <p>Aucune réservation trouvée.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service</th>
                            <th>ID Utilisateur</th>
                            <th>Date de réservation</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reservations as $reservation): ?>
                        <tr>
                            <td><?php echo esc_html($reservation->id); ?></td>
                            <td><?php echo esc_html($reservation->service_nom ?? 'Service supprimé'); ?></td>
                            <td><?php echo esc_html($reservation->utilisateur_id); ?></td>
                            <td><?php echo esc_html($reservation->date_reservation); ?></td>
                            <td>
                                <?php 
                                switch($reservation->statut) {
                                    case 'en_attente':
                                        echo '<span class="badge badge-warning">En attente</span>';
                                        break;
                                    case 'confirmé':
                                        echo '<span class="badge badge-success">Confirmé</span>';
                                        break;
                                    case 'annulé':
                                        echo '<span class="badge badge-danger">Annulé</span>';
                                        break;
                                    default:
                                        echo esc_html($reservation->statut);
                                }
                                ?>
                            </td>
                            <td>
                                <div class="reservation-actions">
                                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" 
                                          style="display:inline-block; margin-right:10px;">
                                        <?php wp_nonce_field('modifier_statut_reservation_nonce'); ?>
                                        <input type="hidden" name="action" value="modifier_statut_reservation">
                                        <input type="hidden" name="reservation_id" value="<?php echo esc_attr($reservation->id); ?>">
                                        <select name="nouveau_statut" onchange="this.form.submit()">
                                            <option value="en_attente" <?php selected($reservation->statut, 'en_attente'); ?>>En attente</option>
                                            <option value="confirmé" <?php selected($reservation->statut, 'confirmé'); ?>>Confirmé</option>
                                            <option value="annulé" <?php selected($reservation->statut, 'annulé'); ?>>Annulé</option>
                                        </select>
                                    </form>
    
                                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" 
                                          style="display:inline-block;"
                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette réservation ?');">
                                        <?php wp_nonce_field('supprimer_reservation_nonce'); ?>
                                        <input type="hidden" name="action" value="supprimer_reservation">
                                        <input type="hidden" name="reservation_id" value="<?php echo esc_attr($reservation->id); ?>">
                                        <input type="submit" value="Supprimer" class="button button-secondary button-small">
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
    
            <!-- Débogage supplémentaire -->
            <div style="margin-top: 20px; background-color: #f4f4f4; padding: 10px; border: 1px solid #ddd;">
                <h3>Informations de débogage</h3>
                <p><strong>Nombre de réservations :</strong> <?php echo count($reservations); ?></p>
                <pre><?php print_r($reservations); ?></pre>
            </div>
        </div>
        <style>
        .badge {
            display: inline-block;
            padding: 0.25em 0.5em;
            border-radius: 0.25rem;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        </style>
        <?php
    }

    public function handle_modifier_statut_reservation() {
        // Vérification du nonce
        check_admin_referer('modifier_statut_reservation_nonce');
    
        // Vérification des permissions
        if (!current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires.');
        }
    
        // Récupération des données
        $reservation_id = intval($_POST['reservation_id']);
        $nouveau_statut = sanitize_text_field($_POST['nouveau_statut']);
    
        // Mise à jour du statut
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservation_rendez_vous';
        
        $resultat = $wpdb->update(
            $table_name,
            array('statut' => $nouveau_statut),
            array('id' => $reservation_id),
            array('%s'),
            array('%d')
        );
    
        // Redirection avec message de succès ou d'erreur
        if ($resultat) {
            wp_redirect(admin_url('admin.php?page=reservations_liste&message=statut_updated'));
        } else {
            wp_redirect(admin_url('admin.php?page=reservations_liste&message=statut_update_error'));
        }
        exit();
    }
    
    public function handle_supprimer_reservation() {
        // Vérification du nonce
        check_admin_referer('supprimer_reservation_nonce');
    
        // Vérification des permissions
        if (!current_user_can('manage_options')) {
            wp_die('Vous n\'avez pas les permissions nécessaires.');
        }
    
        // Récupération de l'ID de la réservation
        $reservation_id = intval($_POST['reservation_id']);
    
        // Suppression de la réservation
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservation_rendez_vous';
        $resultat = $wpdb->delete(
            $table_name,
            array('id' => $reservation_id),
            array('%d')
        );
    
        // Redirection avec message de succès ou d'erreur
        if ($resultat) {
            wp_redirect(admin_url('admin.php?page=reservations_liste&message=reservation_deleted'));
        } else {
            wp_redirect(admin_url('admin.php?page=reservations_liste&message=reservation_delete_error'));
        }
        exit();
    }
}

// Initialisation du plugin
function initialiser_reservation_ticketing() {
    new ReservationTicketingPlugin();
}
add_action('plugins_loaded', 'initialiser_reservation_ticketing');


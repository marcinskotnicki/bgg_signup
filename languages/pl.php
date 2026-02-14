<?php
/**
 * Polish Language File for BGG Signup System
 */

return [
    // Language name (displayed in language selector)
    '_language_name' => 'Polski',
    
    // Admin Panel
    'admin_panel' => 'Panel Administracyjny',
    'admin_login' => 'Logowanie Administratora',
    'admin_password' => 'Hasło Administratora',
    'login' => 'Zaloguj',
    'logout' => 'Wyloguj',
    'invalid_password' => 'Nieprawidłowe hasło!',
    'invalid_credentials' => 'Nieprawidłowy email lub hasło!',
    'not_admin_user' => 'To konto nie ma uprawnień administratora!',
    'admin_email' => 'Email Administratora',
    'view_site' => 'Zobacz Stronę',
    
    // Admin Tabs
    'tab_add_event' => 'Dodaj Nowe Wydarzenie',
    'tab_options' => 'Opcje',
    'tab_logs' => 'Logi',
    'tab_update' => 'Aktualizuj System',
    
    // Add Event Form
    'add_new_event' => 'Dodaj Nowe Wydarzenie',
    'event_name' => 'Nazwa Wydarzenia',
    'number_of_days' => 'Liczba Dni',
    'day_number' => 'Dzień {number}',
    'date' => 'Data',
    'start_time' => 'Godzina Rozpoczęcia',
    'end_time' => 'Godzina Zakończenia',
    'number_of_tables' => 'Liczba Stołów',
    'create_event' => 'Utwórz Wydarzenie',
    'event_created_success' => 'Wydarzenie utworzone pomyślnie!',
    'event_creation_error' => 'Błąd tworzenia wydarzenia: {error}',
    
    // Options
    'system_options' => 'Opcje Systemu',
    'general_settings' => 'Ustawienia Ogólne',
    'venue_name' => 'Nazwa Miejsca',
    'default_event_name' => 'Domyślna Nazwa Wydarzenia',
    'default_start_time' => 'Domyślna Godzina Rozpoczęcia',
    'default_end_time' => 'Domyślna Godzina Zakończenia',
    'timeline_extension' => 'Rozszerzenie Osi Czasu (godziny po zakończeniu)',
    'default_tables' => 'Domyślna Liczba Stołów',
    'bgg_api_token' => 'Token API BoardGameGeek',
    'default_language' => 'Domyślny Język',
    'active_template' => 'Aktywny Szablon',
    
    'smtp_settings' => 'Ustawienia SMTP (do powiadomień email)',
    'smtp_email' => 'Email SMTP',
    'smtp_login' => 'Login SMTP',
    'smtp_password' => 'Hasło SMTP',
    'smtp_server' => 'Serwer SMTP',
    'smtp_port' => 'Port SMTP',
    
    'user_interaction_settings' => 'Ustawienia Interakcji Użytkownika',
    'allow_reserve_list' => 'Pozwól na Listę Rezerwową',
    'allow_logged_in' => 'Pozwól na Zalogowanych Użytkowników',
    'require_emails' => 'Wymagaj Adresów Email',
    'verification_method' => 'Metoda Weryfikacji (do edycji/usuwania)',
    'send_emails' => 'Wysyłaj Powiadomienia Email',
    'allow_full_deletion' => 'Pozwól na Pełne Usuwanie Gier',
    'restrict_comments' => 'Ogranicz Komentarze do Zalogowanych Użytkowników',
    'use_captcha' => 'Użyj CAPTCHA dla Komentarzy',
    
    'custom_messages' => 'Własne Wiadomości',
    'homepage_message' => 'Wiadomość na Stronie Głównej (wyświetlana pod tytułem wydarzenia)',
    'add_game_message' => 'Wiadomość Dodawania Gry (wyświetlana nad formularzem gry)',
    'add_player_message' => 'Wiadomość Zapisu Gracza (wyświetlana nad formularzem zapisu)',
    
    'change_admin_password' => 'Zmień Hasło Administratora',
    'new_admin_password' => 'Nowe Hasło Administratora (zostaw puste aby zachować obecne)',
    
    'save_options' => 'Zapisz Opcje',
    'options_updated_success' => 'Opcje zaktualizowane pomyślnie!',
    'options_and_password_updated' => 'Opcje i hasło zaktualizowane pomyślnie!',
    'options_update_error' => 'Błąd aktualizacji opcji: {error}',
    
    // Options - Dropdowns
    'yes' => 'Tak',
    'no' => 'Nie',
    'login_no' => 'Nie',
    'login_yes' => 'Tak',
    'login_required_games' => 'Wymagane do Dodawania Gier',
    'login_required_all' => 'Wymagane Wszędzie',
    'verification_email' => 'Wymagaj Dopasowania Email',
    'verification_link' => 'Wyślij Link Potwierdzający',
    'deletion_soft_only' => 'Tylko Miękkie Usuwanie',
    'deletion_allow_choice' => 'Pozwól Wybrać (Miękkie lub Twarde)',
    'deletion_hard_only' => 'Tylko Twarde Usuwanie',
    'deletion_mode' => 'Tryb Usuwania Gier',
    'deletion_mode_help' => 'Miękkie usuwanie pozwala przywrócić gry. Twarde usuwanie jest trwałe.',
    'delete_choice_prompt' => 'Kliknij OK dla miękkiego usunięcia (można przywrócić) lub Anuluj dla trwałego usunięcia.',
    'confirm_hard_delete' => 'TRWALE usunąć tę grę? Nie można tego cofnąć!',
    'confirm_hard_delete_game' => 'TRWALE usunąć tę grę? Nie można tego cofnąć!',
    
    // Logs
    'activity_logs' => 'Logi Aktywności',
    'no_logs_found' => 'Nie znaleziono jeszcze logów.',
    'show_logs_from' => 'Pokaż logi z',
    'today' => 'Dzisiaj',
    'last_100_entries' => 'Ostatnie 100 wpisów',
    'all_logs' => 'Wszystkie logi',
    'filter' => 'Filtruj',
    'log_entries_shown' => 'wpisów logów wyświetlonych',
    'timestamp' => 'Znacznik czasu',
    'user' => 'Użytkownik',
    'ip_address' => 'Adres IP',
    'action' => 'Akcja',
    'details' => 'Szczegóły',
    'logs_directory_not_found' => 'Katalog logów nie został znaleziony. Logi będą tworzone automatycznie przy wykonywaniu akcji.',
    
    // Update System
    'update_system' => 'Aktualizuj System',
    'update_warning' => 'Uwaga: Ta opcja srawdzi aktualizacje na GitHub i zastosuje je w systemie. Kopia zapasowa zostanie utworzona automatycznie przed jakimikolwiek zmianami.',
    'github_repository' => 'Repozytorium GitHub',
    'run_update' => 'Uruchom Aktualizację',
    'update_confirm' => 'Czy na pewno chcesz uruchomić aktualizację? Kopia zapasowa zostanie utworzona.',
    'update_completed_success' => 'Aktualizacja zakończona pomyślnie!',
    'update_failed' => 'Aktualizacja nie powiodła się! Sprawdź logi dla szczegółów.',
    'update_log' => 'Log Aktualizacji',
    
    // Messages
    'success' => 'Sukces',
    'error' => 'Błąd',
    'warning' => 'Ostrzeżenie',
    
    // Frontend - Game Display
    'game_name' => 'Nazwa Gry',
    'play_time' => 'Czas Gry',
    'players' => 'Gracze',
    'difficulty' => 'Trudność',
    'language' => 'Język',
    'host' => 'Gospodarz',
    'rules_explanation' => 'Wyjaśnienie Zasad',
    'rules_will_be_explained' => 'Tłumaczę zasady',
    'rules_knowledge_required' => 'Nie tłumaczę zasad',
    'minutes' => 'minut',
    'min_max_players' => '{min}-{max} graczy',
    
    // Frontend - Player List
    'join_game' => 'Dołącz do Gry',
    'join_reserve' => 'Dołącz do Listy Rezerwowej',
    'reserve_list' => 'Lista Rezerwowa',
    'player_name' => 'Imię Gracza',
    'player_email' => 'Email',
    'knows_rules' => 'Czy znasz zasady?',
    'knows_rules_yes' => 'Tak',
    'knows_rules_no' => 'Nie',
    'knows_rules_somewhat' => 'Trochę',
    'comment' => 'Komentarz',
    'additional_comment' => 'Dodatkowy Komentarz',
    'sign_up' => 'Zapisz Się',
    
    // Frontend - Game Actions
    'add_new_table' => 'Dodaj Nowy Stół',
    'add_game_to_table' => 'Dodaj Grę do Tego Stołu',
    'edit' => 'Edytuj',
    'delete' => 'Usuń',
    'restore' => 'Przywróć',
    'fully_delete' => 'Usuń Całkowicie',
    
    // Frontend - Add Game
    'search_bgg' => 'Szukaj na BoardGameGeek',
    'add_game_manual' => 'Dodaj Grę Ręcznie',
    'search_game' => 'Wyszukaj grę',
    'add_game' => 'Dodaj Grę',
    'game_details' => 'Szczegóły Gry',
    'select_thumbnail' => 'Wybierz Miniaturę',
    'host_name' => 'Twoje Imię',
    'host_email' => 'Twój Email',
    'join_as_first_player' => 'Czy chcesz również dołączyć do tej gry jako pierwszy gracz?',
    'language_independent' => 'Niezależna językowo',
    
    // Frontend - Comments
    'comments' => 'Komentarze',
    'add_comment' => 'Dodaj Komentarz',
    'post_comment' => 'Opublikuj Komentarz',
    'author_name' => 'Twoje Imię',
    
    // Frontend - Timeline
    'timeline' => 'Oś Czasu',
    'table' => 'Stół',
    
    // Frontend - Messages
    'no_active_event' => 'Brak aktywnego wydarzenia w tym momencie.',
    'loading' => 'Ładowanie...',
    
    // Validation Messages
    'field_required' => 'To pole jest wymagane',
    'invalid_email' => 'Podaj prawidłowy adres email',
    'passwords_dont_match' => 'Hasła nie pasują do siebie',
    'password_too_short' => 'Hasło musi mieć co najmniej 6 znaków',
    'all_fields_required' => 'Wszystkie pola są wymagane',
    'email_already_exists' => 'Ten adres email jest już zarejestrowany',
    'registration_failed' => 'Rejestracja nie powiodła się. Spróbuj ponownie.',
    'user_not_found' => 'Użytkownik nie znaleziony',
    'incorrect_current_password' => 'Obecne hasło jest nieprawidłowe',
    'update_failed' => 'Aktualizacja nie powiodła się. Spróbuj ponownie.',
    
    // Login/Register
    'register' => 'Zarejestruj się',
    'email' => 'Email',
    'password' => 'Hasło',
    'name' => 'Imię',
    'confirm_password' => 'Potwierdź Hasło',
    'password_min_6_chars' => 'Co najmniej 6 znaków',
    'dont_have_account' => 'Nie masz konta?',
    'register_now' => 'Zarejestruj się teraz',
    'already_have_account' => 'Masz już konto?',
    'login_here' => 'Zaloguj się tutaj',
    'back_to_homepage' => 'Powrót do strony głównej',
    
    // User Profile
    'user_profile' => 'Profil Użytkownika',
    'your_activity' => 'Twoja Aktywność',
    'games_created' => 'Utworzonych Gier',
    'games_joined' => 'Dołączonych do Gier',
    'comments_made' => 'Napisanych Komentarzy',
    'update_profile' => 'Aktualizuj Profil',
    'profile_update_info' => 'Musisz podać swoje obecne hasło aby potwierdzić zmiany w profilu.',
    'basic_information' => 'Podstawowe Informacje',
    'display_name' => 'Wyświetlana Nazwa',
    'name_visible_to_others' => 'Ta nazwa będzie widoczna dla innych użytkowników',
    'email_used_for_login' => 'Używany do logowania i powiadomień',
    'change_password' => 'Zmień Hasło',
    'new_password' => 'Nowe Hasło',
    'leave_blank_keep_current' => 'Zostaw puste aby zachować obecne hasło',
    'confirm_changes' => 'Potwierdź Zmiany',
    'current_password' => 'Obecne Hasło',
    'required_to_confirm_changes' => 'Wymagane do potwierdzenia zmian',
    'save_changes' => 'Zapisz Zmiany',
    'profile_updated_successfully' => 'Profil zaktualizowany pomyślnie!',
    
    // Additional UI
    'player' => 'Gracz',
    'resign' => 'Rezygnuj',
    'day' => 'Dzień',
    'no_games_yet' => 'Nie dodano jeszcze gier do tego stołu.',
    'no_event_description' => 'Sprawdź później lub skontaktuj się z organizatorem.',
    'login_required_to_add_game' => 'Musisz być zalogowany aby dodać grę.',
    'login_required_to_add_table' => 'Musisz być zalogowany aby dodać stół.',
    'login_required_to_join' => 'Musisz być zalogowany aby dołączyć do gry.',
    'error_adding_table' => 'Błąd dodawania stołu.',
    'confirm_resign' => 'Czy na pewno chcesz zrezygnować z tej gry?',
    'confirm_delete_game' => 'Czy na pewno chcesz usunąć tę grę?',
    'confirm_soft_delete' => 'Miękkie usunięcie (możliwe przywrócenie)?',
    'confirm_hard_delete' => 'TRWALE usunąć? Nie można tego cofnąć!',
    'confirm_fully_delete' => 'Czy na pewno chcesz trwale usunąć tę grę? Nie można tego cofnąć!',
    'error_occurred' => 'Wystąpił błąd. Spróbuj ponownie.',
    
    // Email subjects
    'email_subject_player_joined' => 'Nowy gracz dołączył: {game}',
    'email_subject_player_joined_reserve' => 'Nowy gracz dołączył do listy rezerwowej: {game}',
    'email_subject_player_resigned' => 'Gracz zrezygnował: {game}',
    'email_subject_promoted_from_reserve' => 'Zostałeś awansowany z listy rezerwowej: {game}',
    'email_subject_game_changed' => 'Zmieniono szczegóły gry: {game}',
    'email_subject_game_deleted' => 'Gra odwołana: {game}',
    'email_subject_comment_added' => 'Nowy komentarz do: {game}',
    
    // Email bodies
    'email_body_player_joined' => '<strong>{player}</strong> dołączył do Twojej gry <strong>{game}</strong>.<br><br>Wydarzenie: {event}<br>Data: {date}<br>Godzina: {time}',
    'email_body_player_joined_reserve' => '<strong>{player}</strong> dołączył do listy rezerwowej dla Twojej gry <strong>{game}</strong>.<br><br>Wydarzenie: {event}<br>Data: {date}<br>Godzina: {time}',
    'email_body_player_resigned' => '<strong>{player}</strong> zrezygnował z Twojej gry <strong>{game}</strong>.<br><br>Wydarzenie: {event}<br>Data: {date}<br>Godzina: {time}',
    'email_body_promoted_from_reserve' => 'Dobre wieści! Zwolniło się miejsce w <strong>{game}</strong> i zostałeś awansowany z listy rezerwowej.<br><br>Wydarzenie: {event}<br>Data: {date}<br>Godzina: {time}',
    'email_body_game_changed' => 'Szczegóły gry <strong>{game}</strong> zostały zaktualizowane.<br><br>Wydarzenie: {event}<br>Data: {date}<br>Godzina: {time}',
    'email_body_game_deleted' => 'Niestety, gra <strong>{game}</strong> została odwołana.<br><br>Wydarzenie: {event}<br>Data: {date}<br>Godzina: {time}',
    'email_body_comment_added' => '<strong>{author}</strong> dodał komentarz do <strong>{game}</strong>:<br><br>{comment}',
    
    // Email footers
    'email_footer_view_game' => 'Zobacz Grę',
    'email_footer_view_event' => 'Zobacz Wydarzenie',
    'email_automated_message' => 'To jest automatyczna wiadomość. Prosimy nie odpowiadać.',
    'changes' => 'Zmiany',
    
    // Form labels
    'search_or_add_manually' => 'Szukaj na BoardGameGeek lub dodaj ręcznie',
    'enter_game_name' => 'Proszę wpisać nazwę gry',
    'no_results_found' => 'Nie znaleziono wyników',
    'loading_game_details' => 'Ładowanie szczegółów gry',
    'saving' => 'Zapisywanie',
    'back' => 'Powrót',
    'min_players' => 'Minimalna liczba graczy',
    'max_players' => 'Maksymalna liczba graczy',
    'cancel' => 'Anuluj',
    'select_option' => 'Wybierz opcję',
    'restore_game' => 'Przywróć Grę',
    'players_signed_up' => 'zapisanych graczy',
    'restore_game_info' => 'Przywracając tę grę, zostaniesz nowym gospodarzem. Wszyscy wcześniej zapisani gracze pozostaną.',
    'your_name' => 'Twoje Imię',
    'your_email' => 'Twój Email',
    'login_required_to_comment' => 'Musisz być zalogowany aby dodawać komentarze.',
    'existing_comments' => 'Istniejące Komentarze',
    'captcha_question' => 'Ile to jest {num1} + {num2}?',
    
    // System głosowania
    'create_poll' => 'Utwórz Głosowanie',
    'create_game_poll' => 'Utwórz Głosowanie na Grę',
    'poll_info_text' => 'Utwórz głosowanie, aby pozwolić graczom zagłosować na grę. Gdy gra osiągnie wymaganą liczbę głosów, zostanie automatycznie dodana do harmonogramu, a głosowanie zostanie zamknięte.',
    'your_information' => 'Twoje Dane',
    'add_game_option' => 'Dodaj Opcję Gry',
    'add_game_to_poll' => 'Dodaj Gry do Głosowania',
    'poll_options' => 'Opcje Głosowania',
    'add_to_poll' => 'Dodaj do Głosowania',
    'game_poll' => 'Głosowanie na Grę',
    'vote_for_game' => 'Głosuj na Grę',
    'vote_threshold' => 'Wymagana liczba głosów',
    'votes' => 'głosów',
    'vote_for_this' => 'Głosuj',
    'submit_vote' => 'Oddaj Głos',
    'email_notification_on_poll_close' => 'Otrzymasz email gdy głosowanie się zakończy',
    'winner' => 'Zwycięzca',
    'created_by' => 'Utworzono przez',
    'closed' => 'Zamknięte',
    'email_subject_poll_closed' => 'Głosowanie zakończone: wybrano {game}',
    'email_body_poll_closed' => 'Głosowanie zostało zakończone i wybrano <strong>{game}</strong> na wydarzenie <strong>{event}</strong>!',
    
    // Wiadomości prywatne
    'allow_private_messages' => 'Pozwól na Wiadomości Prywatne',
    'allow_private_messages_help' => 'Pozwól użytkownikom wysyłać prywatne wiadomości do graczy przez email',
    'send_private_message' => 'Wyślij Wiadomość Prywatną',
    'send_message_to_all_players' => 'Wyślij wiadomość do wszystkich graczy w tej grze',
    'message_sent_successfully' => 'Wiadomość wysłana pomyślnie',
    'to' => 'Do',
    'regarding' => 'Dotyczy',
    'message' => 'Wiadomość',
    'send_message' => 'Wyślij Wiadomość',
    'sending' => 'Wysyłanie',
    'reply_to_address' => 'Odbiorcy mogą odpowiedzieć bezpośrednio na ten adres',
    'all_players' => 'Wszyscy Gracze',
    
    // Zarządzanie użytkownikami
    'tab_users' => 'Użytkownicy',
    'user_management' => 'Zarządzanie Użytkownikami',
    'role' => 'Rola',
    'admin' => 'Administrator',
    'user' => 'Użytkownik',
    'actions' => 'Akcje',
    'make_admin' => 'Nadaj Uprawnienia Admina',
    'make_user' => 'Odbierz Uprawnienia Admina',
    'reset_password' => 'Zresetuj Hasło',
    
    // Date formatting - Month names (short - genitive case for Polish)
    '_months_short' => [
        1 => 'sty', 2 => 'lut', 3 => 'mar', 4 => 'kwi', 5 => 'maj', 6 => 'cze',
        7 => 'lip', 8 => 'sie', 9 => 'wrz', 10 => 'paź', 11 => 'lis', 12 => 'gru'
    ],
    
    // Date formatting - Month names (full - genitive case for Polish dates)
    '_months_full' => [
        1 => 'stycznia', 2 => 'lutego', 3 => 'marca', 4 => 'kwietnia', 5 => 'maja', 6 => 'czerwca',
        7 => 'lipca', 8 => 'sierpnia', 9 => 'września', 10 => 'października', 11 => 'listopada', 12 => 'grudnia'
    ],
    
    // Date formatting - Day names (full)
    '_days_full' => [
        0 => 'niedziela', 1 => 'poniedziałek', 2 => 'wtorek', 3 => 'środa',
        4 => 'czwartek', 5 => 'piątek', 6 => 'sobota'
    ],
];
?>
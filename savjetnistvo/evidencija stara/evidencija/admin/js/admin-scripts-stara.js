jQuery(document).ready(function($) {

    // Spremanje trenutno dohvaćenih podataka o odabranoj sobi
    var currentRoomCapacity = null;
    var currentRoomOccupied = null;
    var currentRoomId = null;
    var roomDetailsRequest = null;
    var roomsByLocationRequest = null;

    // Jedinstvena, efikasna funkcija za inicijalizaciju datepicker-a
    function initDatepickers(context) {
        // Ako je context zadan (npr. novododani element), traži unutar njega.
        // U suprotnom, pretražuje cijeli dokument.
        var $elements = context ? $(context).find('.evidencija-datepicker') : $('.evidencija-datepicker');

        $elements.not('.hasDatepicker').datepicker({
            dateFormat: 'dd-mm-yy',
            changeMonth: true,
            changeYear: true,
            yearRange: "-100:+10",
            onSelect: function(dateText, inst) {
                validateDateFormat($(this));
            }
        });
    }

    // Inicijalna inicijalizacija na učitavanju stranice
    initDatepickers();

    // Funkcija za validaciju formata datuma
    function validateDateFormat($inputField) {
        var dateString = $inputField.val();
        var regex_dash = /^\d{2}-\d{2}-\d{4}$/; // DD-MM-YYYY
        var regex_dot = /^\d{2}\.\d{2}\.\d{4}$/; // ISPRAVKA: Uklonjena točka s kraja - DD.MM.YYYY
        var regex_iso = /^\d{4}-\d{2}-\d{2}$/; // ISO-MM-DD

        var isValid = false;

        if (dateString === '') {
            isValid = true; // Prazan string je dozvoljen za opcionalna polja
        } else if (regex_dash.test(dateString) || regex_dot.test(dateString) || regex_iso.test(dateString)) {
            // Dodatna provjera za ispravnost datuma (npr. 30.02.2024. nije validan datum)
            var parts;
            var day, month, year;

            if (regex_dash.test(dateString)) {
                parts = dateString.split('-');
                day = parseInt(parts[0], 10);
                month = parseInt(parts[1], 10);
                year = parseInt(parts[2], 10);
            } else if (regex_dot.test(dateString)) {
                parts = dateString.split('.');
                day = parseInt(parts[0], 10);
                month = parseInt(parts[1], 10);
                year = parseInt(parts[2], 10);
            } else if (regex_iso.test(dateString)) {
                parts = dateString.split('-');
                year = parseInt(parts[0], 10);
                month = parseInt(parts[1], 10);
                day = parseInt(parts[2], 10);
            }

            // Provjera da li je datum validan (npr. 30. veljače)
            var d = new Date(year, month - 1, day);
            if (d.getFullYear() === year && d.getMonth() + 1 === month && d.getDate() === day) {
                isValid = true;
            }
        }

        var $errorSpan = $inputField.next('.date-error-message');
        if (!isValid && dateString !== '') {
            if ($errorSpan.length === 0) {
                $inputField.after('<span class="date-error-message" style="color: red; display: block;">' + evidencija_vars.i18n.datum_greska_format + '</span>');
            }
        } else {
            if ($errorSpan.length > 0) {
                $errorSpan.remove();
            }
        }
        return isValid;
    }

    // Validacija datuma prilikom promjene polja
    $(document).on('change', '.evidencija-datepicker', function() {
        validateDateFormat($(this));
    });

    // Prilikom slanja forme, provjerite sve datepickere
    $('form').on('submit', function() {
        var allDatesValid = true;
        $('.evidencija-datepicker').each(function() {
            if (!validateDateFormat($(this))) {
                allDatesValid = false;
            }
        });
        if (!allDatesValid) {
            alert(evidencija_vars.i18n.provjeri_datume); // Generic alert for invalid dates
            return false; // Spriječi slanje forme
        }

        // Dodatna validacija za sobu popunjenost prilikom slanja korisničke forme
        var $statusSmjestaja = $('#status_smjestaja');
        if ($statusSmjestaja.val() === 'Trenutno smješten') {
            var $sobaId = $('#soba_id');
            var selectedRoomOption = $sobaId.find('option:selected');
            var roomCapacity = currentRoomCapacity !== null ? parseInt(currentRoomCapacity, 10) : parseInt(selectedRoomOption.data('capacity'));
            var roomOccupied = currentRoomOccupied !== null ? parseInt(currentRoomOccupied, 10) : parseInt(selectedRoomOption.data('occupied'));
            var userId = $('#user_id').val(); // Dohvati ID korisnika za ažuriranje

            // Provjeri je li odabrana soba uopće
            if (!selectedRoomOption.val()) { // Nema odabrane sobe
                alert(evidencija_vars.i18n.odaberite_sobu);
                return false;
            }

            // U PHP-u već hendlamo validaciju kapaciteta, ovo je samo dodatni frontend check za UX
            // Provjeri je li nova soba dodijeljena I je li popunjena
            var selectedRoomIdCurrent = parseInt($sobaId.val(), 10);
            var selectedRoomIdOriginal = parseInt(evidencija_vars.selectedRoomId, 10);
            if (!userId || (userId && selectedRoomIdCurrent !== selectedRoomIdOriginal)) {
                 if (roomOccupied >= roomCapacity) {
                    alert(evidencija_vars.i18n.soba_puna);
                    return false; // Spriječi slanje
                }
            }
        }

        return true;
    });


    // -------- Logika za dinamičko dodavanje/uklanjanje polja (Plaćanja, Kontakti, Medicinski Podaci) --------

    // Kontakti obitelji
    var kontaktIndex = evidencija_vars.kontaktIndex;
    $('#add-kontakt-obitelji').on('click', function() {
        var template = $('#kontakt-obitelji-template').html();
        template = template.replace(/__INDEX__/g, kontaktIndex);
        var $newItem = $(template);

        // Popunjavanje tekstova labela iz prevedenih varijabli
        $newItem.find('label .label-text').eq(0).text(evidencija_vars.i18n.ime);
        $newItem.find('label .label-text').eq(1).text(evidencija_vars.i18n.prezime);
        $newItem.find('label .label-text').eq(2).text(evidencija_vars.i18n.telefon);
        $newItem.find('label .label-text').eq(3).text(evidencija_vars.i18n.email);
        $newItem.find('label .label-text').eq(4).text(evidencija_vars.i18n.odnos_s_korisnikom);
        $newItem.find('button.remove-kontakt').text(evidencija_vars.i18n.ukloni_kontakt);

        $('#kontakti-obitelji-wrapper').append($newItem);
        kontaktIndex++;
        initDatepickers($newItem); // ISPRAVKA: Efikasnija reinicijalizacija samo na novom elementu
    });

    $('#kontakti-obitelji-wrapper').on('click', '.remove-kontakt', function() {
        $(this).closest('.kontakt-obitelji-item').remove();
    });

    // Medicinski podaci
    var medicinskiIndex = evidencija_vars.medicinskiIndex;
    $('#add-medicinski-podatak').on('click', function() {
        var template = $('#medicinski-podatak-template').html();
        template = template.replace(/__INDEX__/g, medicinskiIndex);
        var $newItem = $(template);

        // Popunjavanje tekstova labela i opcija selecta
        $newItem.find('label .label-text').eq(0).text(evidencija_vars.i18n.tip);
        $newItem.find('select option[value="lijek"]').text(evidencija_vars.i18n.lijek);
        $newItem.find('select option[value="dijagnoza"]').text(evidencija_vars.i18n.dijagnoza);
        $newItem.find('select option[value="alergija"]').text(evidencija_vars.i18n.alergija);
        $newItem.find('select option[value="ostalo"]').text(evidencija_vars.i18n.ostalo);
        $newItem.find('label .label-text').eq(1).text(evidencija_vars.i18n.opis);
        $newItem.find('button.remove-medicinski-podatak').text(evidencija_vars.i18n.ukloni_medicinski_podatak);

        $('#medicinski-podaci-wrapper').append($newItem);
        medicinskiIndex++;
        initDatepickers($newItem); // ISPRAVKA: Efikasnija reinicijalizacija
    });

    $('#medicinski-podaci-wrapper').on('click', '.remove-medicinski-podatak', function() {
        $(this).closest('.medicinski-podatak-item').remove();
    });

    // Logika za uklanjanje postojećih dokumenata
    var deletedDocuments = [];
    $('#current-documents-list').on('click', '.remove-document', function() {
        var documentId = $(this).data('document-id');
        if (confirm(evidencija_vars.i18n.potvrdi_brisanje_dokumenta)) { 
            deletedDocuments.push(documentId);
            $('#deleted-documents-ids').val(JSON.stringify(deletedDocuments));
            $(this).closest('li').remove();
        }
    });


    // -------- Logika za upravljanje sobama unutar lokacije (location-add-edit.php) --------
    var roomIndex = evidencija_vars.roomIndex || 0; 
    $('#add-room').on('click', function() {
        var template = $('#room-template').html();
        template = template.replace(/__INDEX__/g, roomIndex);
        var $newItem = $(template);

        // Popunjavanje tekstova labela iz prevedenih varijabli
        $newItem.find('label .label-text-room-name').text(evidencija_vars.i18n.naziv_sobe);
        $newItem.find('label .label-text-room-capacity').text(evidencija_vars.i18n.kapacitet_sobe);
        $newItem.find('button.remove-room').text(evidencija_vars.i18n.ukloni_sobu);

        $('#rooms-wrapper').append($newItem);
        roomIndex++;
    });

    $('#rooms-wrapper').on('click', '.remove-room', function() {
        if (confirm(evidencija_vars.i18n.potvrdi_brisanje_sobe)) {
            var roomId = $(this).closest('.room-item').find('input[name*="[id]"]').val();
            if (roomId && roomId !== '0') {
                var deletedRooms = $('#deleted-rooms-ids').val() ? JSON.parse($('#deleted-rooms-ids').val()) : [];
                deletedRooms.push(roomId);
                $('#deleted-rooms-ids').val(JSON.stringify(deletedRooms));
            }
            $(this).closest('.room-item').remove();
        }
    });


    // -------- Logika za dinamički odabir sobe u user-add-edit.php --------
    // Nova funkcija koja će se pozivati pri promjeni lokacije ili inicijalno
    function loadRoomsForLocation(selectedLocationId, selectedRoomId_onLoad) {
        var $sobaSelect = $('#soba_id');

        $sobaSelect.empty();
        $('#room-occupancy-info').text(''); // Očisti info o popunjenosti
        $('#bed-number-row').hide(); // Sakrij polje za krevet dok se sobe ne učitaju

        if (roomsByLocationRequest) {
            roomsByLocationRequest.abort();
        }

        if (selectedLocationId) {
            roomsByLocationRequest = $.ajax({
                url: evidencija_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'evidencija_get_rooms_by_location',
                    location_id: selectedLocationId,
                    nonce: evidencija_vars.nonce_get_rooms
                },
                success: function(response) {
                    if ($('#lokacija_id').val() !== selectedLocationId) {
                        return;
                    }
                    if (response.success) {
                        if (response.data.has_rooms) {
                            $sobaSelect.append($('<option>', {
                                value: '',
                                text: evidencija_vars.i18n.odaberite_sobu
                            }));
                            $sobaSelect.append(response.data.html);
                        } else {
                            $sobaSelect.append($('<option>', {
                                value: '',
                                text: evidencija_vars.i18n.odaberite_sobu
                            }));
                            $('#room-occupancy-info').text(evidencija_vars.i18n.nema_soba);
                        }
                        if (selectedRoomId_onLoad && response.data.has_rooms && $sobaSelect.find('option[value="' + selectedRoomId_onLoad + '"]').length) {
                            $sobaSelect.val(selectedRoomId_onLoad);
                        }
                        $sobaSelect.trigger('change');
                    } else {
                        $sobaSelect.append($('<option>', { value: '', text: evidencija_vars.i18n.odaberite_sobu }));
                        $('#room-occupancy-info').text(evidencija_vars.i18n.nema_soba);
                    }
                },
                error: function(jqXHR, textStatus) {
                    if (textStatus === 'abort' || $('#lokacija_id').val() !== selectedLocationId) {
                        return;
                    }
                    $sobaSelect.append($('<option>', { value: '', text: evidencija_vars.i18n.odaberite_sobu }));
                    $('#room-occupancy-info').text(evidencija_vars.i18n.nema_soba);
                },
                complete: function() {
                    roomsByLocationRequest = null;
                }
            });
        }
    }

    // Pozovi loadRoomsForLocation kada se promijeni lokacija
    $('#lokacija_id').on('change', function() {
        var selectedLocationId = $(this).val();
        // Važno: kada korisnik ručno mijenja lokaciju, poništi selectedRoomId_onLoad da ne forsira stari odabir
        loadRoomsForLocation(selectedLocationId, null); 
    });

    // Prikaz/skrivanje polja Soba i Krevet ovisno o statusu smještaja
    $('#status_smjestaja').on('change', function() {
        var status = $(this).val();
        if (status === 'Trenutno smješten') {
            $('#room-selection-row').show();
            $('#soba_id').prop('required', true);
            // Prilikom prikaza sobe, ponovno učitaj sobe za trenutnu lokaciju, poštujući originalni odabir
            loadRoomsForLocation($('#lokacija_id').val(), evidencija_vars.selectedRoomId);
        } else {
            $('#room-selection-row').hide();
            $('#bed-number-row').hide();
            $('#soba_id').prop('required', false).val(''); // Reset soba selection
            $('#room-occupancy-info').text(''); // Clear occupancy info
        }
    }).trigger('change'); // Pokreni pri učitavanju stranice

    // Prikaz informacija o popunjenosti sobe i korisnicima pri odabiru sobe
    $('#soba_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var capacity = selectedOption.data('capacity');
        var occupied = selectedOption.data('occupied');
        var roomId = selectedOption.val();
        var $occupancyInfo = $('#room-occupancy-info');
        var $bedNumberRow = $('#bed-number-row');
        var $occupantsList = $('#room-occupants-list');
        $occupantsList.empty();

        if (roomDetailsRequest) {
            roomDetailsRequest.abort();
        }

        if (roomId) {
            // Dohvati svježe podatke o sobi (uključujući listu korisnika)
            roomDetailsRequest = $.ajax({
                url: evidencija_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'evidencija_get_room_details',
                    room_id: roomId,
                    nonce: evidencija_vars.nonce_get_room_details
                },
                success: function(resp) {
                    if ($('#soba_id').val() !== roomId) {
                        return;
                    }
                    if (resp.success) {
                        $occupantsList.empty();
                        capacity = resp.data.capacity;
                        occupied = resp.data.occupied;
                        currentRoomCapacity = capacity;
                        currentRoomOccupied = occupied;
                        currentRoomId = parseInt(roomId, 10);
                        selectedOption.data('capacity', capacity);
                        selectedOption.data('occupied', occupied);

                        var userId = $('#user_id').val();
                        var selectedRoomIdOriginal = parseInt(evidencija_vars.selectedRoomId, 10);
                        var selectedRoomIdCurrent = parseInt(roomId, 10);

                        if (typeof sprintf === 'function') {
                            $occupancyInfo.text(sprintf(
                                evidencija_vars.i18n.popunjenost_sobe_format,
                                occupied,
                                capacity
                            ));
                        } else {
                            $occupancyInfo.text('Trenutna popunjenost: ' + occupied + '/' + capacity);
                        }

                        var optionText = resp.data.room_name + ' (popunjeno: ' + occupied + '/' + capacity + ')';
                        selectedOption.text(optionText);

                        if (resp.data.occupants && resp.data.occupants.length > 0) {
                            var addedIds = {};
                            resp.data.occupants.forEach(function(o) {
                                if (addedIds[o.id]) {
                                    return; // skip duplicates
                                }
                                addedIds[o.id] = true;
                                var li = $('<li>')
                                    .text(o.name)
                                    .attr('data-user-id', o.id);
                                if (userId && parseInt(userId, 10) === o.id) {
                                    li.addClass('current-user')
                                      .append(' ' + evidencija_vars.i18n.trenutni_korisnik);
                                }
                                $occupantsList.append(li);
                            });
                        }
                    } else {
                        $occupancyInfo.text('');
                    }
                },
                error: function() {
                    if ($('#soba_id').val() !== roomId) {
                        return;
                    }
                    currentRoomCapacity = capacity;
                    currentRoomOccupied = occupied;
                    currentRoomId = parseInt(roomId, 10);
                    selectedOption.data('capacity', capacity);
                    selectedOption.data('occupied', occupied);
                    if (typeof sprintf === 'function') {
                        $occupancyInfo.text(sprintf(
                            evidencija_vars.i18n.popunjenost_sobe_format,
                            occupied,
                            capacity
                        ));
                    } else {
                        $occupancyInfo.text('Trenutna popunjenost: ' + occupied + '/' + capacity);
                    }

                    var optionText = selectedOption.text().split(' (popunjeno')[0] + ' (popunjeno: ' + occupied + '/' + capacity + ')';
                    selectedOption.text(optionText);
                },
                complete: function() {
                    roomDetailsRequest = null;
                }
            });
            $bedNumberRow.show();
        } else {
            $occupancyInfo.text('');
            $bedNumberRow.hide();
        }
    });

    // Inicijalni poziv za učitavanje soba i postavljanje originalne sobe pri učitavanju stranice
    // Ovo treba biti zadnje u ready bloku
    var initialLocationId = $('#lokacija_id').val();
    if (initialLocationId) {
        loadRoomsForLocation(initialLocationId, evidencija_vars.selectedRoomId);
    }

    // -------- Logika za export izvješća --------
    $(document).on('click', '.export-report-button', function() {
        var reportType = $(this).data('report-type');
        var reportParams = $(this).data('report-params') || {}; // Dohvati parametre ako postoje

        // Kreiraj privremenu formu za slanje POST zahtjeva kako bi se aktivirao download
        var $form = $('<form>', {
            'action': evidencija_vars.ajax_url,
            'method': 'POST',
            'target': '_blank' // Otvori u novom tabu
        }).appendTo('body');

        // Dodaj potrebne inpute
        $('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'evidencija_export_report'
        }).appendTo($form);

        $('<input>', {
            'type': 'hidden',
            'name': 'report_type',
            'value': reportType
        }).appendTo($form);

        $('<input>', {
            'type': 'hidden',
            'name': 'nonce',
            'value': evidencija_vars.nonce_export_report
        }).appendTo($form);

        // Dodaj parametre izvješća (npr. godina, mjesec, lokacija)
        $('<input>', {
            'type': 'hidden',
            'name': 'report_params',
            'value': JSON.stringify(reportParams) // Pošalji parametre kao JSON string
        }).appendTo($form);

        $form.submit().remove(); // Pošalji formu i odmah je ukloni iz DOM-a
    });

    // -------- Logika za ispis kartona u PDF (preko preglednika) --------
    $('#print-karton-button').on('click', function() {
        var printContents = '';

        // Funkcija za dohvaćanje sadržaja pojedinog postboxa
        function getPostboxContent(postboxId, title) {
            var $postbox = $('#' + postboxId);
            if ($postbox.length === 0) return '';

            var contentHtml = '<div style="margin-bottom: 20px;">';
            // Samo dodajte h2 ako je title validan (ne undefined i nije prazan)
            if (title && typeof title === 'string' && title.trim() !== '') {
                contentHtml += '<h2>' + title + '</h2>';
            }
            
            // Prikaz tabličnih podataka (npr. Osnovni podaci, Podaci o smještaju)
            var $formTable = $postbox.find('.form-table');
            if ($formTable.length > 0) {
                contentHtml += '<table style="width:100%; border-collapse: collapse;">';
                $formTable.find('tr').each(function() {
                    var label = $(this).find('th label').text() || $(this).find('th').text();
                    var value = '';
                    var $valueCell = $(this).find('td');

                    if ($valueCell.find('input[type="text"], input[type="email"], input[type="number"]').length > 0) {
                        value = $valueCell.find('input').val();
                    } else if ($valueCell.find('select').length > 0) {
                        value = $valueCell.find('select option:selected').text();
                    } else if ($valueCell.find('textarea').length > 0) {
                        value = $valueCell.find('textarea').val();
                    } else if ($(this).find('input[type="radio"]:checked').length > 0) { // Provjeri radio gumbe unutar tr-a
                        value = $(this).find('input[type="radio"]:checked').val() || ''; // Vrijednost odabranog radio gumba
                        // Ako radio gumb nema izravnu labelu, pokušaj dohvatiti tekst prateće labele
                        if (!value && $(this).find('input[type="radio"]:checked').length > 0) {
                            var $checkedRadio = $(this).find('input[type="radio"]:checked');
                            value = $checkedRadio.next('label').text().trim() || $checkedRadio.parent('label').text().trim();
                        }
                    } else {
                        // ISPRAVKA: Dohvaćanje teksta, ignorirajući child elemente
                        value = $valueCell.clone().children().remove().end().text().trim();
                    }
                    if (label) { // Izbjegni prazne labele (kao kod radio buttona gdje je labela prazna)
                         contentHtml += '<tr><td style="border: 1px solid #ccc; padding: 8px; font-weight: bold;">' + label + '</td><td style="border: 1px solid #ccc; padding: 8px;">' + value + '</td></tr>';
                    }
                });
                contentHtml += '</table>';
            }

            // Posebno za dinamičke liste (kontakti, medicinski)
            var $kontaktiWrapper = $postbox.find('#kontakti-obitelji-wrapper');
            if ($kontaktiWrapper.length > 0 && $kontaktiWrapper.find('.kontakt-obitelji-item').length > 0) {                $kontaktiWrapper.find('.kontakt-obitelji-item').each(function(index) {
                    var itemDetails = [];
                    // Iteriraj kroz labele i inpute unutar svakog kontakta
                    $(this).find('label').each(function() {
                        var labelText = $(this).find('span.label-text').text() || $(this).text().replace(':', '').trim();
                        var itemValue = '';
                        if ($(this).find('input[type="text"], input[type="email"], input[type="number"]').length > 0) {
                            itemValue = $(this).find('input').val();
                        } else if ($(this).find('select').length > 0) {
                            itemValue = $(this).find('select option:selected').text();
                        } else if ($(this).find('textarea').length > 0) {
                            itemValue = $(this).find('textarea').val();
                        }
                        if (labelText && itemValue) { // Dodaj samo ako labela i vrijednost postoje
                            itemDetails.push('<strong>' + labelText + '</strong>: ' + itemValue);
                        }
                    });
                    if (itemDetails.length > 0) {
                        contentHtml += '<div style="border: 1px solid #eee; padding: 10px; margin-top: 10px;">' + itemDetails.join('<br>') + '</div>';
                    }
                });
            }

            var $medicinskiWrapper = $postbox.find('#medicinski-podaci-wrapper');
            if ($medicinskiWrapper.length > 0 && $medicinskiWrapper.find('.medicinski-podatak-item').length > 0) {                 $medicinskiWrapper.find('.medicinski-podatak-item').each(function(index) {
                    var itemDetails = [];
                    $(this).find('label').each(function() {
                        var labelText = $(this).find('span.label-text').text() || $(this).text().replace(':', '').trim();
                        var itemValue = '';
                        if ($(this).find('input[type="text"]').length > 0) {
                            itemValue = $(this).find('input').val();
                        } else if ($(this).find('select').length > 0) {
                            itemValue = $(this).find('select option:selected').text();
                        } else if ($(this).find('textarea').length > 0) {
                            itemValue = $(this).find('textarea').val();
                        }
                        if (labelText && itemValue) { // Dodaj samo ako labela i vrijednost postoje
                            itemDetails.push('<strong>' + labelText + '</strong>: ' + itemValue);
                        }
                    });
                    if (itemDetails.length > 0) {
                        contentHtml += '<div style="border: 1px solid #eee; padding: 10px; margin-top: 10px;">' + itemDetails.join('<br>') + '</div>';
                    }
                });
            }


            // Za opće bilješke
            var $textarea = $postbox.find('textarea#opce_biljeske');
            if ($textarea.length > 0 && $textarea.val().trim() !== '') { // Provjeri da nije prazno
                contentHtml += '<p>' + $textarea.val().replace(/\n/g, '<br>') + '</p>';
            }

            // Za dokumente
            var $documentsList = $postbox.find('#current-documents-list');
            if ($documentsList.length > 0 && $documentsList.find('li').length > 0) { // Provjeri da ima dokumenata␊
                contentHtml += '<ul>';
                $documentsList.find('li').each(function() {
                    contentHtml += '<li>' + $(this).find('a').text() + '</li>';
                });
                contentHtml += '</ul>';
            }


            contentHtml += '</div>'; // Zatvori div za postbox
            return contentHtml;
        }
        
        var userName = $('#ime').val() + ' ' + $('#prezime').val();
        var userLocation = $('#lokacija_id option:selected').text();
        var userRoom = $('#soba_id option:selected').text();
        var userBedNumber = $('#broj_kreveta').val();

        printContents += '<h1>' + userName + ' - ' + evidencija_vars.i18n.karton_korisnika + '</h1>';
        printContents += '<p>' + evidencija_vars.i18n.datum_ispisa + ': ' + new Date().toLocaleDateString() + '</p>';
        
        // Dodani prijevodi za Lokacija, Soba, Broj kreveta
        if (userLocation && userLocation !== evidencija_vars.i18n.odaberite_lokaciju) {
            printContents += '<p><strong>' + evidencija_vars.i18n.lokacija + '</strong>: ' + userLocation + '</p>';
        }
        if (userRoom && userRoom !== evidencija_vars.i18n.odaberite_sobu && userRoom !== evidencija_vars.i18n.nema_soba) {
            printContents += '<p><strong>' + evidencija_vars.i18n.soba + '</strong>: ' + userRoom + '</p>';
        }
        if (userBedNumber && userBedNumber.trim() !== '') { // Provjeri da broj kreveta nije prazan string
            printContents += '<p><strong>' + evidencija_vars.i18n.broj_kreveta + '</strong>: ' + userBedNumber + '</p>';
        }
        printContents += '<hr>';

        // POZIVI ZA getPostboxContent s ispravnim ID-evima postboxova
        // Naslove postboxa sada prosljeđujemo kao drugi argument, a funkcija getPostboxContent ih prikazuje kao <h2>
        printContents += getPostboxContent('postbox-basic-data', evidencija_vars.i18n.osnovni_podaci);
        printContents += getPostboxContent('postbox-accommodation-data', evidencija_vars.i18n.podaci_o_smjestaju);
        printContents += getPostboxContent('postbox-contact-data', evidencija_vars.i18n.kontakt_podaci_obitelji_staratelja);
        printContents += getPostboxContent('postbox-medical-data', evidencija_vars.i18n.medicinski_podaci_print);
        printContents += getPostboxContent('postbox-documents', evidencija_vars.i18n.prilozeni_dokumenti_print);
        printContents += getPostboxContent('postbox-general-notes', evidencija_vars.i18n.opce_biljeske_print);

        var printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>' + userName + ' - Karton</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; font-size: 10pt; }');
        printWindow.document.write('h1, h2, h3, h4 { color: #333; margin-top: 15px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }');
        printWindow.document.write('h1 { font-size: 18pt; } h2 { font-size: 14pt; } h3 { font-size: 12pt; } h4 { font-size: 10pt; }'); // Ažurirani stilovi za naslove
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }');
        printWindow.document.write('th { background-color: #f2f2f2; font-weight: bold; }');
        printWindow.document.write('ul { list-style-type: disc; margin-left: 20px; padding-left: 0; }');
        printWindow.document.write('p.description { font-style: italic; color: #666; }');
        printWindow.document.write('pre { white-space: pre-wrap; word-wrap: break-word; font-size: 8pt; background-color: #f8f8f8; border: 1px solid #eee; padding: 5px; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContents);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
        // printWindow.onafterprint = function() { printWindow.close(); }; // Opcionalno: Zatvori prozor nakon ispisa/spremanja
    });

});
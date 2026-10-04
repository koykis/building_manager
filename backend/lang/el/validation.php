<?php
return [
 'required'=>'Το πεδίο :attribute είναι υποχρεωτικό.', 'present'=>'Το πεδίο :attribute πρέπει να υπάρχει.',
 'string'=>'Το πεδίο :attribute πρέπει να είναι κείμενο.', 'integer'=>'Το πεδίο :attribute πρέπει να είναι ακέραιος.',
 'numeric'=>'Το πεδίο :attribute πρέπει να είναι αριθμός.', 'boolean'=>'Το πεδίο :attribute πρέπει να είναι αληθές ή ψευδές.',
 'email'=>'Το email δεν είναι έγκυρο.', 'array'=>'Το πεδίο :attribute πρέπει να είναι λίστα.',
 'date_format'=>'Το πεδίο :attribute πρέπει να έχει μορφή :format.', 'in'=>'Μη έγκυρη επιλογή για :attribute.',
 'exists'=>'Δεν βρέθηκε η επιλεγμένη τιμή για :attribute.', 'unique'=>'Η τιμή του πεδίου :attribute υπάρχει ήδη.',
 'distinct'=>'Υπάρχει διπλή τιμή στο πεδίο :attribute.', 'regex'=>'Μη έγκυρη μορφή στο πεδίο :attribute.',
 'prohibited'=>'Το πεδίο :attribute δεν επιτρέπεται.', 'file'=>'Το πεδίο :attribute πρέπει να είναι αρχείο.',
 'mimes'=>'Επιτρεπόμενοι τύποι αρχείου: :values.',
 'min'=>['string'=>'Το πεδίο :attribute χρειάζεται τουλάχιστον :min χαρακτήρες.','numeric'=>'Ελάχιστη τιμή: :min.'],
 'max'=>['string'=>'Το πεδίο :attribute επιτρέπει έως :max χαρακτήρες.','numeric'=>'Μέγιστη τιμή: :max.','array'=>'Επιτρέπονται έως :max στοιχεία.','file'=>'Μέγιστο μέγεθος αρχείου: :max KB.'],
];

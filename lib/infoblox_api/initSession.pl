#!/usr/bin/perl

use Infoblox;

#INICIO DE SESION
my $session = Infoblox::Session->new(
     master   => "ARG_address",  #Required
     password => "ARG_password",  #Required
     username => "ARG_user",  #Required
);

#COMPROBACION SESION
if ($session->status_code()) {
    die("Construct session failed: ",
        $session->status_code() . ":" . $session->status_detail());
}
#print "Session created successfully\n";
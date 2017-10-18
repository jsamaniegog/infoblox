#OBTENCION DE DATOS
my @retrieved_objs = $session->search(
    object => "ARG_object",
    ARG_field   => 'ARG_pattern'
);
my $object = $retrieved_objs[0];
unless ($object) {
    die("Search ARG_field failed: ", $session->status_code() . ":" . $session->status_detail());
}
#print "Search DNS A object using regexp found at least 1 matching entry\n";

foreach my $match (@retrieved_objs) {
    print $match->ARG_method() . "\n";
}
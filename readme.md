# wp-publications

wp-publications integrates bibtexbrowser into wordpress.
It enables research groups and individuals to add publication lists in Wordpress. The publication lists are generated on the fly from bibtex files using [bibtexbrowser](http://www.monperrus.net/martin/bibtexbrowser). 

## Installation

Download https://github.com/monperrus/wp-publications/archive/refs/heads/master.zip, upload it as plugin to your wordpress and activate it. 

## Usage

One just has to create a post/page containing a short code such as:

+ [wp-publications bib="mybibliography.bib" all=true] 
+ [wp-publications bib="mybibliography.bib" year=2011] 
+ [wp-publications bib="mybibliography.bib" author="Martin Monperrus"] 

One can also mix options:

+ [wp-publications bib="mybibliography.bib" all=true academic=true] 
+ [wp-publications bib="mybibliography.bib" year=2011 author="Martin Monperrus" academic=true] 

Notes:

1. The short code options exactly correspond to [bibtexbrowser](http://www.monperrus.net/martin/bibtexbrowser) queries.
1. The mybibliography.bib should be encoded in UTF-8 if diacritics are not LaTeX-escaped

For bug reports and discussion, please post an issue here


test:
	composer test

ai:
	./merge.bash \
	--output=ai.txt \
	--folder-recursive="." \
	--ignore-folders=vendor \
	--ignore-folders=test_files \
	--ignore-extensions=lock,bash \
	--ignore-files=LICENSE.md \
	--ignore-files=makefile

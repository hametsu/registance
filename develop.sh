### github push

git add .
echo ":: Github commit message :: "
echo ">"
read ACT
git commit -m "${ACT}"
git push

### deploy

scp -P 1002 * esehara@www45045.sakura.ne.jp:/var/www/esehara/registance
scp -P 1002 lib/* esehara@www45045.sakura.ne.jp:/var/www/esehara/registance/lib


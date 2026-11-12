<?php

namespace YesWiki\Identity\SignIn\Contract;

enum HandshakeMode: string
{
    case Real = 'real';
    case Test = 'test';
}

import re
from . import statement as _statement

class Handle:
    def __init__(self, handle):
        self.handle = handle

    def run(self, statement, parameters):
        statement, parameters, extraneousParameters = self.__explodeParams(statement, parameters)
        print({'statement': statement, 'parameters': parameters, 'extraneousParameters': extraneousParameters})
        cursor = self.handle.cursor(cursor_factory=_statement.Statement)
        cursor.execute(statement, parameters)
        return cursor

    def cursor(self):
        return self.handle.cursor()


    def __explodeParams(self, statement, parameters):
        newParameters = {}
        replacementList = {}
        extraneousParameters = []

        for name, value in parameters.items():
            bindingToken, name = self.__varNameAndToken(name)
            # print({'bindingToken': bindingToken, 'name': name})
            # Construct our replacement key, we also use this to verify the existence of the token
            # [a-zA-Z0-9_] matches legal characters for use in bind tokens i.e. :first-param is an
            # invalid bindToken
            searchKey = '(?<!:)' + bindingToken + '(?![a-zA-Z0-9_])'
            # print({'bindingToken': bindingToken, 'searchKey': searchKey})
            if re.search(searchKey, statement) == None:
                # print('Unfound ' + name)
                # We didn't find this name, we're done with it,
                # but keep a record for debugging
                extraneousParameters.append(bindingToken)
            elif isinstance(value, str) or isinstance(value, int):
                # print('Scalar ' + name + ' with value ' + value)
                # This is not a param that needs replacing, retain it,
                replacementList[searchKey] = self.__psycopg2Token(bindingToken)
                newParameters[name] = value
            else:
                # print('Array ' + name)
                keys = []
                if hasattr(value, 'keys'):
                    keys = value.keys
                else:
                    keys = list(range(0, len(value)))
                # Then mix the indicies of the array into the name to
                # create a bunch of unique keys for our individual elements
                # print({'keys': keys})
                newKeys = list(map(lambda a : '0' + name + '__' + str(a), keys))
                # Add a new 'to-be-replaced' mapping for the query substituting
                # our new list of comma imploded names for the old name
                replacementList[searchKey] = ', '.join(list(map(self.__psycopg2Token, newKeys)))
                # Finally combine the values with their new keys and add
                # them to the list of parameters
                newParameters = {**newParameters, **dict(zip(newKeys, value))}
                # print({'newParameters': newParameters, 'additive': dict(zip(newKeys, value)), 'newKeys': newKeys, 'value': value})
        for searchToken, replacement in replacementList.items():
            statement = re.sub(searchToken, replacement, statement)
        return statement, newParameters, extraneousParameters

    # Hey look it's [1] - that'll mean something eventually
    def __psycopg2Token(self, token):
        if token[0] == ':':
            return '%(' + token[1:] + ')s'
        else:
            return '%(' + token + ')s'

    def __varNameAndToken(self, variable):
        if str(variable) and variable[0] == ':':
            return variable, variable[1:]
        else:
            return ':' + variable, variable
